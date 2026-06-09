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
use Espo\Modules\Sales\Entities\PurchaseOrder;
use Espo\Modules\Sales\Entities\Quote;
use Espo\Modules\Sales\Entities\ReceiptOrder;
use Espo\Modules\Sales\Entities\ReceiptOrderReceivedItem;
use Espo\Modules\Sales\Entities\ReturnOrder;
use Espo\Modules\Sales\Entities\SalesOrder;
use Espo\Modules\Sales\Entities\SubscriptionItem;
use Espo\Modules\Sales\Entities\SubscriptionUpdate;
use Espo\Modules\Sales\Entities\SupplierBill;
use Espo\Modules\Sales\Entities\SupplierCredit;

class OrderEntityUtil
{
    public static function getItemEntityType(string $entityType): string
    {
        if ($entityType === SubscriptionUpdate::ENTITY_TYPE) {
            return SubscriptionItem::ENTITY_TYPE;
        }

        return $entityType . 'Item';
    }

    public static function getItemParentEntityType(string $itemEntityType): string
    {
        if ($itemEntityType === ReceiptOrderReceivedItem::ENTITY_TYPE) {
            return ReceiptOrder::ENTITY_TYPE;
        }

        return substr($itemEntityType, 0, -4);
    }

    public static function getItemParentField(string $itemEntityType): string
    {
        if ($itemEntityType === ReceiptOrderReceivedItem::ENTITY_TYPE) {
            return 'receiptOrder';
        }

        return lcfirst(substr($itemEntityType, 0, -4));
    }

    public static function isWithTax(string $entityType): bool
    {
        return self::isSalesWithTax($entityType) || self::isPurchaseWithTax($entityType);
    }

    public static function isWithTaxCashBasis(string $entityType): bool
    {
        return in_array($entityType, [
            Invoice::ENTITY_TYPE,
            CreditNote::ENTITY_TYPE,
            SupplierBill::ENTITY_TYPE,
            SupplierCredit::ENTITY_TYPE,
        ]);
    }

    /**
     * @return string[]
     */
    public static function getEntityTypesWithRoundingProfile(): array
    {
        return [
            Invoice::ENTITY_TYPE,
            CreditNote::ENTITY_TYPE,
        ];
    }

    /**
     * @return string[]
     */
    public static function getEntityTypesWithTax(): array
    {
        return [
            Quote::ENTITY_TYPE,
            SalesOrder::ENTITY_TYPE,
            Invoice::ENTITY_TYPE,
            CreditNote::ENTITY_TYPE,
            ReturnOrder::ENTITY_TYPE,
            PurchaseOrder::ENTITY_TYPE,
            SupplierBill::ENTITY_TYPE,
            SupplierCredit::ENTITY_TYPE,
        ];
    }

    public static function isSalesWithTax(string $entityType): bool
    {
        return in_array($entityType, [
            Quote::ENTITY_TYPE,
            SalesOrder::ENTITY_TYPE,
            Invoice::ENTITY_TYPE,
            CreditNote::ENTITY_TYPE,
            ReturnOrder::ENTITY_TYPE,
        ]);
    }

    public static function isPurchaseWithTax(string $entityType): bool
    {
        return in_array($entityType, [
            PurchaseOrder::ENTITY_TYPE,
            SupplierBill::ENTITY_TYPE,
            SupplierCredit::ENTITY_TYPE,
        ]);
    }

    /**
     * @return string[]
     */
    public static function getEntityTypesWithPaymentTermsProfile(): array
    {
        return [
            Invoice::ENTITY_TYPE,
        ];
    }
}
