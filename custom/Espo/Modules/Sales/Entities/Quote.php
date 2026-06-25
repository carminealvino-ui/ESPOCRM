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
use Espo\Modules\Crm\Entities\Contact;
use Espo\Modules\Crm\Entities\Opportunity;
use Espo\Modules\Sales\Tools\Sales\HavingDiscountOrder;
use Espo\Modules\Sales\Tools\Sales\HavingDiscountOrderTrait;
use Espo\Modules\Sales\Tools\Sales\HavingTaxInclusiveOrder;
use Espo\Modules\Sales\Tools\Sales\HavingTaxInclusiveOrderTrait;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\Modules\Sales\Tools\Sales\OrderItem;
use Espo\Modules\Sales\Tools\Tax\TaxableOrder;
use Espo\Modules\Sales\Tools\Tax\TaxableOrderTrait;

/**
 * @extends OrderEntity<OrderItem>
 */
class Quote extends OrderEntity implements TaxableOrder, HavingDiscountOrder, HavingTaxInclusiveOrder
{
    use TaxableOrderTrait;
    use HavingDiscountOrderTrait;
    use HavingTaxInclusiveOrderTrait;

    public const ENTITY_TYPE = 'Quote';

    public const STATUS_APPROVED = 'Approved';

    public function getOpportunity(): ?Opportunity
    {
        /** @var ?Opportunity */
        return $this->relations->getOne('opportunity');
    }

    public function getBillingContact(): ?Contact
    {
        /** @var ?Contact */
        return $this->relations->getOne('billingContact');
    }

    public function getShippingContact(): ?Contact
    {
        /** @var ?Contact */
        return $this->relations->getOne('shippingContact');
    }

    public function getAmount(): ?Currency
    {
        /** @var ?Currency */
        return $this->getValueObject('amount');
    }

    public function getShippingProvider(): ?ShippingProvider
    {
        /** @var ?ShippingProvider */
        return $this->relations->getOne('shippingProvider');
    }

    public function getPriceBook(): ?PriceBook
    {
        /** @var ?PriceBook */
        return $this->relations->getOne('priceBook');
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
}
