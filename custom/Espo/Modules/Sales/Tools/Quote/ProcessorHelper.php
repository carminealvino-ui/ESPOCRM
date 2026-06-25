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

namespace Espo\Modules\Sales\Tools\Quote;

use Espo\Core\Currency\CalculatorUtil;
use Espo\Core\Currency\ConfigDataProvider as CurrencyConfig;
use Espo\Core\Field\Currency;
use Espo\Modules\Sales\Entities\CreditNote;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Entities\PurchaseOrder;
use Espo\Modules\Sales\Entities\Quote;
use Espo\Modules\Sales\Entities\ReturnOrder;
use Espo\Modules\Sales\Entities\SalesOrder;
use Espo\Modules\Sales\Entities\SupplierBill;
use Espo\Modules\Sales\Entities\SupplierCredit;
use Espo\Modules\Sales\Entities\TaxCode;
use Espo\Modules\Sales\Entities\TaxLineItem;
use Espo\Modules\Sales\Tools\Sales\HavingCurrencyRateEntity;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\Modules\Sales\Tools\Tax\CalculationItem;
use Espo\ORM\EntityManager;
use RuntimeException;

class ProcessorHelper
{
    /** @var array<string, TaxCode> */
    private array $taxCodeCache = [];

    public function __construct(
        private CurrencyConfig $currencyConfig,
        private EntityManager $entityManager,
        private RoundingUtil $roundingUtil,
        private CurrencyConverterUtil $currencyConverterUtil,
    ) {}

    public function createZero(OrderEntity $order): Currency
    {
        return new Currency('0', $this->getCurrency($order));
    }

    public function getCurrency(OrderEntity $order): string
    {
        return $order->getAmountCurrency() ?? $this->currencyConfig->getDefaultCurrency();
    }

    /**
     * @param numeric-string|Currency $amount
     */
    public static function isZero(string|Currency $amount): bool
    {
        if ($amount instanceof Currency) {
            return self::isZero($amount->getAmountAsString());
        }

        return CalculatorUtil::compare($amount, '0') === 0;
    }

    /**
     * @return numeric-string
     */
    public static function getAmountString(
        Quote|SalesOrder|Invoice|CreditNote|PurchaseOrder|ReturnOrder|SupplierBill|SupplierCredit $order,
    ): string {

        return $order->getAmount()?->getAmountAsString() ?? '0';
    }

    public function setTaxLineItemAmounts(
        TaxLineItem $taxLineItem,
        CalculationItem $calculatedItem,
        OrderEntity $order,
    ): void {

        $taxLineItem
            ->setAmount($calculatedItem->amount)
            ->setBaseAmount($calculatedItem->baseAmount->getAmountAsString())
            ->setAmountPrecise($calculatedItem->amountPrecise->getAmountAsString());

        if (!$order instanceof HavingCurrencyRateEntity) {
            return;
        }

        $amountLocalPrecise = $this->currencyConverterUtil->convertToLocal(
            value: $calculatedItem->amountPrecise,
            order: $order,
            round: false,
        );

        $baseAmountLocalPrecise = $this->currencyConverterUtil->convertToLocal(
            value: $calculatedItem->baseAmount,
            order: $order,
            round: false,
        );

        $amountLocalPrecise = $this->roundingUtil->round($amountLocalPrecise, RoundingUtil::FACTOR_PRECISE);
        $baseAmountLocalPrecise = $this->roundingUtil->round($baseAmountLocalPrecise, RoundingUtil::FACTOR_PRECISE);

        $amountLocal = $this->roundingUtil->round($amountLocalPrecise);
        $baseAmountLocal = $this->roundingUtil->round($baseAmountLocalPrecise);

        $taxLineItem
            ->setAmountLocal($amountLocal)
            ->setAmountLocalPrecise($amountLocalPrecise->getAmountAsString())
            ->setBaseAmountLocal($baseAmountLocal->getAmountAsString())
            ->setBaseAmountLocalPrecise($baseAmountLocalPrecise->getAmountAsString());
    }

    /**
     * @return TaxCode
     */
    public function getTaxCode(string $taxCodeId): TaxCode
    {
        if (!array_key_exists($taxCodeId, $this->taxCodeCache)) {
            $taxCode = $this->entityManager->getRDBRepositoryByClass(TaxCode::class)->getById($taxCodeId);

            if (!$taxCode) {
                throw new RuntimeException("Tax code $taxCodeId does not exist.");
            }

            $this->taxCodeCache[$taxCodeId] = $taxCode;
        }

        return $this->taxCodeCache[$taxCodeId];
    }
}
