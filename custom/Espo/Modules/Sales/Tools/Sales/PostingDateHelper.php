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

namespace Espo\Modules\Sales\Tools\Sales;

use Espo\Modules\Sales\Entities\CreditNote;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Entities\PaymentEntry;
use Espo\Modules\Sales\Entities\SupplierBill;
use Espo\Modules\Sales\Entities\SupplierCredit;
use Espo\Modules\Sales\Entities\WriteOffEntry;
use LogicException;

class PostingDateHelper
{
    public function __construct(
        private ConfigDataProvider $configDataProvider,
    ) {}

    public function process((OrderEntity&IssuableOrder)|PaymentEntry|WriteOffEntry $order): void
    {
        $dateField = match ($order->getEntityType()) {
            Invoice::ENTITY_TYPE,SupplierBill::ENTITY_TYPE => Invoice::FIELD_DATE_INVOICED,
            CreditNote::ENTITY_TYPE,SupplierCredit::ENTITY_TYPE => CreditNote::FIELD_DATE_ISSUED,
            PaymentEntry::ENTITY_TYPE =>  PaymentEntry::FIELD_DATE_PAID,
            WriteOffEntry::ENTITY_TYPE =>  WriteOffEntry::FIELD_DATE,
            default => throw new LogicException(),
        };

        if (
            !$order->isAttributeChanged($dateField) ||
            $order->isIssued() && $this->configDataProvider->isIssuanceLockingEnabled() ||
            !$this->toSync($order)
        ) {
            return;
        }

        if ($order->isIssued() || $order->getPostingDate()) {
            $order->set(OrderEntity::FIELD_POSTING_DATE, $order->get($dateField));
        }
    }

    public function toSync((OrderEntity&IssuableOrder)|WriteOffEntry|PaymentEntry $order): bool
    {
        if ($order instanceof PaymentEntry) {
            return !$this->configDataProvider->isPaymentEntryPostingDateEnabled();
        }

        if ($order instanceof SupplierBill || $order instanceof SupplierCredit) {
            return !$this->configDataProvider->isSupplierBillPostingDateEnabled();
        }

        return true;
    }
}
