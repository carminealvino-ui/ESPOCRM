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

namespace Espo\Modules\Sales\Entities;

use Espo\Core\Field\Currency;
use Espo\Core\Field\Date;
use Espo\Core\FieldProcessing\Loader\Params as FieldLoaderParams;
use Espo\Modules\Crm\Entities\Contact;
use Espo\Modules\Sales\Classes\FieldLoaders\Invoice\AmountDue as AmountDueLoader;
use Espo\Modules\Sales\Tools\Sales\HavingCurrencyRateEntity;
use Espo\Modules\Sales\Tools\Sales\HavingCurrencyRateEntityTrait;
use Espo\Modules\Sales\Tools\Sales\HavingLocalAmountsTrait;
use Espo\Modules\Sales\Tools\Sales\IssuableOrder;
use Espo\Modules\Sales\Tools\Sales\IssuableOrderTrait;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\Modules\Sales\Tools\Tax\TaxableOrder;
use Espo\Modules\Sales\Tools\Tax\TaxableOrderTrait;

class SupplierBill extends OrderEntity implements TaxableOrder, IssuableOrder, HavingCurrencyRateEntity
{
    public const ENTITY_TYPE = 'SupplierBill';

    use TaxableOrderTrait;
    use IssuableOrderTrait;
    use HavingCurrencyRateEntityTrait;
    use HavingLocalAmountsTrait;

    public const FIELD_AMOUNT_DUE = 'amountDue';
    public const FIELD_DATE_INVOICED = 'dateInvoiced';
    public const FIELD_DATE_DUE = 'dateDue';
    public const FIELD_SUPPLIER = 'supplier';
    public const FIELD_PURCHASE_ORDER = 'purchaseOrder';
    public const FIELD_BILLING_CONTACT = 'billingContact';

    public const ATTR_SUPPLIER_ID = 'supplierId';

    public const STATUS_DRAFT = 'Draft';
    public const STATUS_PAID = 'Paid';
    public const STATUS_ISSUED = 'Issued';
    public const STATUS_CANCELED = 'Canceled';

    public function getSupplier(): ?Supplier
    {
        /** @var ?Supplier */
        return $this->relations->getOne(self::FIELD_SUPPLIER);
    }

    public function setSupplier(?Supplier $supplier): self
    {
        return $this->setRelatedLinkOrEntity(self::FIELD_SUPPLIER, $supplier);
    }

    public function getPurchaseOrder(): ?PurchaseOrder
    {
        /** @var ?PurchaseOrder */
        return $this->relations->getOne(self::FIELD_PURCHASE_ORDER);
    }

    public function getBillingContact(): ?Contact
    {
        /** @var ?Contact */
        return $this->relations->getOne(self::FIELD_BILLING_CONTACT);
    }

    public function getDateDue(): ?Date
    {
        /** @var ?Date */
        return $this->getValueObject(self::FIELD_DATE_DUE);
    }

    public function setDateDue(?Date $date): self
    {
        $this->setValueObject(self::FIELD_DATE_DUE, $date);

        return $this;
    }

    public function getDateInvoiced(): ?Date
    {
        /** @var ?Date */
        return $this->getValueObject(self::FIELD_DATE_INVOICED);
    }

    public function setDateInvoiced(?Date $date): self
    {
        $this->setValueObject(self::FIELD_DATE_INVOICED, $date);

        return $this;
    }

    public function getAmount(): ?Currency
    {
        /** @var ?Currency */
        return $this->getValueObject(OrderEntity::FIELD_AMOUNT);
    }

    public function getGrandTotalAmount(): ?Currency
    {
        /** @var ?Currency */
        return $this->getValueObject(OrderEntity::FIELD_GRAND_TOTAL_AMOUNT);
    }

    public function getShippingCost(): ?Currency
    {
        /** @var ?Currency */
        return $this->getValueObject(OrderEntity::FIELD_SHIPPING_COST);
    }

    public function setShippingCost(?Currency $cost): self
    {
        return $this->setValueObject(OrderEntity::FIELD_SHIPPING_COST, $cost);
    }

    public function getAmountDue(): ?Currency
    {
        $loader = new AmountDueLoader($this->entityManager);

        $loader->process($this, FieldLoaderParams::create());

        $raw = $this->get(self::FIELD_AMOUNT_DUE);
        $rawCurrency = $this->get(self::FIELD_AMOUNT_DUE . 'Currency');

        return $raw && $rawCurrency ?
            Currency::create($raw, $rawCurrency) : null;
    }
}
