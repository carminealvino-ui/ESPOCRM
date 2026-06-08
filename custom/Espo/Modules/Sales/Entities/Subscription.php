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

use Espo\Core\Field\Date;
use Espo\Core\Field\Link;
use Espo\Modules\Crm\Entities\Contact;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\Modules\Sales\Tools\Sales\OrderItem;
use Espo\Modules\Sales\Tools\Subscription\SubscriptionOrderItem;
use UnexpectedValueException;

/**
 * @extends OrderEntity<SubscriptionOrderItem>
 */
class Subscription extends OrderEntity
{
    public const ENTITY_TYPE = 'Subscription';

    public const STATUS_DRAFT = 'Draft';
    public const STATUS_TRIAL = 'Trial';
    public const STATUS_ACTIVE = 'Active';
    public const STATUS_PAUSED = 'Paused';
    public const STATUS_STOPPED = 'Stopped';

    public const BILLING_STATE_CLEAR = 'Clear';
    public const BILLING_STATE_DUE = 'Due';
    public const BILLING_STATE_PAST_DUE = 'Past Due';

    public const FIELD_STATUS = 'status';
    public const FIELD_BILLING_STATE = 'billingState';
    public const FIELD_ANCHOR_DAY = 'anchorDay';
    public const FIELD_END_DATE = 'endDate';
    public const FIELD_START_DATE = 'startDate';
    public const FIELD_PRIMARY_PRODUCT = 'primaryProduct';
    public const FIELD_BILLING_PLAN = 'billingPlan';
    public const FIELD_HAS_TRIAL = 'hasTrial';

    public const ATTR_BILLING_PLAN_ID = 'billingPlanId';
    public const ATTR_TEMPLATE_ID = 'templateId';

    public const LINK_ACCOUNT = 'account';
    public const LINK_BILLING_CONTACT = 'billingContact';

    public function getBillingPlan(): SubscriptionBillingPlan
    {
        $plan = $this->relations->getOne(self::FIELD_BILLING_PLAN);

        if (!$plan instanceof SubscriptionBillingPlan) {
            throw new UnexpectedValueException();
        }

        return $plan;
    }

    public function isLocked(): bool
    {
        return false;
    }

    public function getBillingContact(): ?Contact
    {
        /** @var ?Contact */
        return $this->relations->getOne(self::LINK_BILLING_CONTACT);
    }

    public function getStartDate(): ?Date
    {
        $raw = $this->get(self::FIELD_START_DATE);

        if (!$raw) {
            return null;
        }

        return Date::fromString($raw);
    }

    public function getEndDate(): ?Date
    {
        $raw = $this->get(self::FIELD_END_DATE);

        if (!$raw) {
            return null;
        }

        return Date::fromString($raw);
    }

    public function setEndDate(?Date $date): self
    {
        return $this->setValueObject(self::FIELD_END_DATE, $date);
    }

    public function getTax(): ?Tax
    {
        /** @var ?Tax */
        return $this->relations->getOne('tax');
    }

    public function getAnchorDay(): ?int
    {
        return $this->get('anchorDay');
    }

    public function setAnchorDay(?int $day): self
    {
        return $this->set('anchorDay', $day);
    }

    public function getInvoiceDuePeriodDays(): ?int
    {
        return null;
    }

    public function getGracePeriodDays(): ?int
    {
        return null;
    }

    public function getBillingState(): string
    {
        return $this->get('billingState');
    }

    public function setBillingState(string $billingState): static
    {
        $this->set('billingState', $billingState);

        return $this;
    }

    public function getPaymentMethod(): ?PaymentMethod
    {
        /** @var ?PaymentMethod */
        return $this->relations->getOne('paymentMethod');
    }

    /**
     * @return SubscriptionOrderItem[]
     */
    public function getItems(): array
    {
        return array_map(function (OrderItem $item) {
            return SubscriptionOrderItem::fromRaw($item->toRaw());
        }, parent::getItems());
    }

    public function setPrimaryProduct(Link|Product|null $product): self
    {
        return $this->setRelatedLinkOrEntity(self::FIELD_PRIMARY_PRODUCT, $product);
    }

    public function setBillingPlan(SubscriptionBillingPlan $billingPlan): self
    {
        return $this->setRelatedLinkOrEntity(self::FIELD_BILLING_PLAN, $billingPlan);
    }

    public function getAmountCurrency(): string
    {
        return $this->get('amountCurrency') ?? throw new UnexpectedValueException("No currency.");
    }

    public function setPaymentMethod(?PaymentMethod $paymentMethod): static
    {
        return $this->setRelatedLinkOrEntity('paymentMethod', $paymentMethod);
    }

    public function getPriceBook(): ?PriceBook
    {
        /** @var ?PriceBook */
        return $this->relations->getOne('priceBook');
    }

    public function getBuyerReference(): ?string
    {
        return $this->get('buyerReference');
    }

    public function getPurchaseOrderReference(): ?string
    {
        return $this->get('purchaseOrderReference');
    }
}
