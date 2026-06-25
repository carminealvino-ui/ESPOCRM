<?php
/***********************************************************************************
 * The contents of this file are subject to the Extension License Agreement
 * ("Agreement") which can be viewed at
 * https://www.espocrm.com/extension-license-agreement/.
 * By copying, installing downloading, or using this file, You have unconditionally
 * agreed to the terms and conditions of the Agreement, and You may not use this
 * file except in compliance with the Agreement. Under the terms of the Agreement,
 * You shall not license, sublicense, sell, resell, rent, lease, lend, distribute,
 * redistribute, market, publish, commercialize, or otherwise transfer rights or
 * usage to the software or any modified version or derivative work of the software
 * created by or for you.
 *
 * Copyright (C) 2015-2026 EspoCRM, Inc.
 *
 * License ID: 11af5a568c1a72dce4e164257d1a0207
 ************************************************************************************/

namespace Espo\Modules\Sales\Tools\PaymentEntry;

use Espo\Core\Currency\ConfigDataProvider as CurrencyConfig;
use Espo\Core\Utils\Metadata;
use Espo\Modules\Sales\Entities\PaymentEntry;
use Espo\Modules\Sales\Tools\Currency\CurrencyRateProvider;
use Espo\Modules\Sales\Tools\Payment\PartyType;
use Espo\Modules\Sales\Tools\Payment\Type;
use Espo\Modules\Sales\Tools\Quote\CurrencyConverterUtil;
use Espo\Modules\Sales\Tools\Sales\ConfigDataProvider;
use Espo\Modules\Sales\Tools\Sales\IssuanceLockingHelper;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use LogicException;

class SetFieldsProcessor
{
    public function __construct(
        private Metadata $metadata,
        private CurrencyConfig $currencyConfig,
        private CurrencyRateProvider $currencyRateProvider,
        private CurrencyConverterUtil $currencyConverterUtil,
        private IssuanceLockingHelper $issuanceLockingHelper,
        private ConfigDataProvider $configDataProvider,
    ) {}

    public function process(PaymentEntry $payment): void
    {
        $this->clearNonRelevant($payment);
        $this->setPartyType($payment);
        $this->setLocal($payment);
        $this->setLocalAmount($payment);
    }

    public function setNumber(PaymentEntry $payment): void
    {
        if (!$this->metadata->get("entityDefs.{$payment->getEntityType()}.fields.number.useAutoincrement")) {
            return;
        }

        if ($payment->getFetched(OrderEntity::FIELD_WAS_ISSUED)) {
            return;
        }

        $field = OrderEntity::FIELD_NUMBER_A;

        if (!$payment->isIssued() && $this->configDataProvider->isDraftNumberingEnabled()) {
            $field = OrderEntity::FIELD_NUMBER_DRAFT_A;
        }

        if (!$payment->isAttributeWritten($field)) {
            return;
        }

        $payment->setNumber($payment->get($field));
    }

    private function setLocal(PaymentEntry $payment): void
    {
        if ($payment->isIssued()) {
            return;
        }

        if (!$payment->getLocalCurrency()) {
            $payment->setLocalCurrency($this->currencyConfig->getBaseCurrency());
        }

        $code = $payment->getAmountCurrency();
        $localCode = $payment->getLocalCurrency() ?? throw new LogicException("No local currency.");

        if ($localCode === $payment->getAmountCurrency()) {
            $payment->setCurrencyRate('1');

            return;
        }

        if ($payment->getCurrencyRate() === null) {
            $rate = $this->currencyRateProvider->get($code, $localCode);

            $payment->setCurrencyRate($rate);
        }
    }

    private function setLocalAmount(PaymentEntry $payment): void
    {
        if ($payment->isIssued() && $this->issuanceLockingHelper->isEnabled()) {
            return;
        }

        $localCode = $payment->getLocalCurrency();
        $rate = $payment->getCurrencyRate();

        if ($localCode === null || $rate === null) {
            return;
        }

        $amountLocal = $this->currencyConverterUtil->convertToLocal($payment->getAmount(), $payment);

        $payment->setAmountLocal($amountLocal);
    }

    private function setPartyType(PaymentEntry $payment): void
    {
        if ($payment->isIssued()) {
            return;
        }

        if (
            !$payment->isNew() &&
            !$payment->isAttributeChanged(PaymentEntry::FIELD_PARTY_TYPE) &&
            !$payment->isAttributeChanged(PaymentEntry::FIELD_ACCOUNT . 'Id') &&
            !$payment->isAttributeChanged(PaymentEntry::FIELD_SUPPLIER . 'Id')
        ) {
            return;
        }

        if ($payment->getPartyType() === PartyType::Customer) {
            $payment->setSupplier(null);
        }

        if ($payment->getPartyType() === PartyType::Supplier) {
            $account = $payment->getSupplier()?->getAccount();

            $payment->setAccount($account);
        }
    }

    private function clearNonRelevant(PaymentEntry $payment): void
    {
        if ($payment->isIssued()) {
            return;
        }

        if ($payment->getType() === Type::Outbound) {
            $payment->setRequest(null);
        }
    }
}
