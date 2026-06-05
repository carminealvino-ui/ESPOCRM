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
use Espo\Core\Field\Link;
use Espo\Modules\Crm\Entities\Contact;
use Espo\Modules\Sales\Tools\Sales\HavingDiscountOrder;
use Espo\Modules\Sales\Tools\Sales\HavingDiscountOrderTrait;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\Modules\Sales\Tools\Sales\OrderItem;
use Espo\Modules\Sales\Tools\Tax\TaxableOrder;
use Espo\Modules\Sales\Tools\Tax\TaxableOrderTrait;

/**
 * @extends OrderEntity<OrderItem>
 */
class PurchaseOrder extends OrderEntity implements TaxableOrder, HavingDiscountOrder
{
    use TaxableOrderTrait;
    use HavingDiscountOrderTrait;

    public const ENTITY_TYPE = 'PurchaseOrder';

    public const FIELD_BILLING_CONTACT = 'billingContact';

    public const STATUS_RELEASED = 'Released';

    public function getSupplier(): ?Link
    {
        /** @var ?Link */
        return $this->getValueObject('supplier');
    }

    /**
     * @todo Replace with `getSupplier` when v9.0 is min supported.
     */
    public function getSupplierEntity(): ?Supplier
    {
        /** @var ?Supplier */
        return $this->relations->getOne('supplier');
    }

    public function isReceiptFullyCreated(): bool
    {
        return $this->get('isReceiptFullyCreated');
    }

    public function getWarehouse(): ?Link
    {
        /** @var ?Link */
        return $this->getValueObject('warehouse');
    }

    public function getShippingContact(): ?Contact
    {
        /** @var ?Contact */
        return $this->relations->getOne('shippingContact');
    }

    public function getShippingProvider(): ?ShippingProvider
    {
        /** @var ?ShippingProvider */
        return $this->relations->getOne('shippingProvider');
    }

    public function getAmount(): ?Currency
    {
        /** @var ?Currency */
        return $this->getValueObject('amount');
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

    public function getBillingContact(): ?Contact
    {
        /** @var ?Contact */
        return $this->relations->getOne(self::FIELD_BILLING_CONTACT);
    }
}
