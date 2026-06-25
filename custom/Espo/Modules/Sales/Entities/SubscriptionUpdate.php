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
use Espo\Modules\Crm\Entities\Account;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\Modules\Sales\Tools\Sales\OrderItem;
use Espo\Modules\Sales\Tools\Subscription\SubscriptionOrderItem;
use Traversable;
use UnexpectedValueException;

class SubscriptionUpdate extends OrderEntity
{
    public const ENTITY_TYPE = 'SubscriptionUpdate';

    public const STATUS_APPLIED = 'Applied';
    public const STATUS_DRAFT = 'Draft';
    public const STATUS_CANCELED = 'Canceled';

    public const BILLING_STATUS_PENDING = 'Pending';
    public const BILLING_STATUS_INVOICED = 'Invoiced';
    public const BILLING_STATUS_SETTLED = 'Settled';
    public const BILLING_STATUS_CANCELED = 'Canceled';

    public const ATTR_SUBSCRIPTION_ID = 'subscriptionId';

    public const FIELD_STATUS = 'status';
    public const FIELD_BILLING_STATUS = 'billingStatus';
    public const FIELD_DATE = 'date';

    public const LINK_SUBSCRIPTION = 'subscription';
    public const LINK_INVOICES = 'invoices';
    public const LINK_CREDIT_NOTES = 'creditNotes';

    public const BILLING_ACTION_ISSUE = 'Issue';
    public const FIELD_BILLING_ACTION = 'billingAction';
    public const FIELD_CREATE_PAYMENT_REQUEST = 'createPaymentRequest';

    /**
     * @throws UnexpectedValueException
     */
    public function getSubscription(): Subscription
    {
        $subscription = $this->relations->getOne('subscription');

        if (!$subscription instanceof Subscription) {
            throw new UnexpectedValueException("No subscription.");
        }

        return $subscription;
    }

    public function getStatus(): string
    {
        return $this->get('status');
    }

    public function getBillingStatus(): string
    {
        return $this->get('billingStatus');
    }

    public function setBillingStatus(string $billingStatus): self
    {
        return $this->set('billingStatus', $billingStatus);
    }

    public function getDate(): Date
    {
        $raw = $this->get('date');

        if (!$raw) {
            throw new UnexpectedValueException("No date.");
        }

        return Date::fromString($raw);
    }


    public function setDate(Date $date): self
    {
        return $this->setValueObject('date', $date);
    }

    public function setSubscription(Subscription $subscription): self
    {
        return $this->setRelatedLinkOrEntity('subscription', $subscription);
    }

    /**
     * @return Traversable<int, Invoice>
     */
    public function getInvoices(): Traversable
    {
        /** @var Traversable<int, Invoice> */
        return $this->relations->getMany(self::LINK_INVOICES);
    }

    /**
     * @return Traversable<int, CreditNote>
     */
    public function getCreditNotes(): Traversable
    {
        /** @var Traversable<int, CreditNote> */
        return $this->relations->getMany(self::LINK_CREDIT_NOTES);
    }

    public function getAccount(): ?Link
    {
        return null;
    }

    public function getAccountEntity(): ?Account
    {
        return null;
    }

    public function isLocked(): bool
    {
        // @todo ?
        return false;
    }

    public function getAmountCurrency(): string
    {
        return $this->get('amountCurrency') ?? throw new UnexpectedValueException("No currency.");
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

    public function issueBilling(): bool
    {
        return $this->get(self::FIELD_BILLING_ACTION) === self::BILLING_ACTION_ISSUE;
    }

    public function createPaymentRequest(): bool
    {
        return (bool) $this->get(self::FIELD_CREATE_PAYMENT_REQUEST);
    }

    public function sendPaymentRequest(): bool
    {
        return (bool) $this->get('sendPaymentRequest');
    }

    public function sendInvoice(): bool
    {
        return (bool) $this->get('sendInvoice');
    }
}
