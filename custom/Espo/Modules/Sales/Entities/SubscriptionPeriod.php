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
use Espo\Core\ORM\Entity;
use Traversable;
use UnexpectedValueException;

class SubscriptionPeriod extends Entity
{
    public const ENTITY_TYPE = 'SubscriptionPeriod';

    public const TYPE_REGULAR = 'Regular';
    public const TYPE_TRIAL = 'Trial';
    public const TYPE_PAUSE = 'Pause';

    public const STATUS_SCHEDULED = 'Scheduled';
    public const STATUS_ACTIVE = 'Active';
    public const STATUS_ENDED = 'Ended';
    public const STATUS_CANCELED = 'Canceled';

    public const BILLING_STATUS_PENDING = 'Pending';
    public const BILLING_STATUS_INVOICED = 'Invoiced';
    public const BILLING_STATUS_SETTLED = 'Settled';
    public const BILLING_STATUS_CANCELED = 'Canceled';

    public const ATTR_SUBSCRIPTION_ID = 'subscriptionId';

    public const FIELD_START_DATE = 'startDate';
    public const FIELD_END_DATE = 'endDate';
    public const FIELD_TYPE = 'type';
    public const FIELD_STATUS = 'status';
    public const FIELD_BILLING_STATUS = 'billingStatus';
    public const FIELD_INVOICE_AUTOMATICALLY = 'invoiceAutomatically';
    public const FIELD_HOLD_UNTIL_BILLING_COMPLETE = 'holdUntilBillingComplete';

    public const LINK_SUBSCRIPTION = 'subscription';
    public const LINK_INVOICES = 'invoices';

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

    public function getType(): string
    {
        return $this->get('type');
    }

    public function setType(string $type): self
    {
        return $this->set('type', $type);
    }

    public function getStatus(): string
    {
        return $this->get('status');
    }

    public function setStatus(string $status): self
    {
        return $this->set('status', $status);
    }

    public function getBillingStatus(): string
    {
        return $this->get('billingStatus');
    }

    public function setBillingStatus(string $billingStatus): self
    {
        return $this->set('billingStatus', $billingStatus);
    }

    public function getStartDate(): Date
    {
        $raw = $this->get('startDate');

        if (!$raw) {
            throw new UnexpectedValueException("No start date.");
        }

        return Date::fromString($raw);
    }

    public function getEndDate(): Date
    {
        $raw = $this->get('endDate');

        if (!$raw) {
            throw new UnexpectedValueException("No end date.");
        }

        return Date::fromString($raw);
    }

    public function setStartDate(Date $startDate): self
    {
        return $this->setValueObject('startDate', $startDate);
    }

    public function setEndDate(Date $endDate): self
    {
        return $this->setValueObject('endDate', $endDate);
    }

    public function setSubscription(Subscription $subscription): self
    {
        return $this->setRelatedLinkOrEntity('subscription', $subscription);
    }

    public function setInvoiceAutomatically(bool $invoiceAutomatically): self
    {
        return $this->set('invoiceAutomatically', $invoiceAutomatically);
    }

    public function setHoldUntilBillingComplete(bool $holdUntilBillingComplete): self
    {
        return $this->set(self::FIELD_HOLD_UNTIL_BILLING_COMPLETE, $holdUntilBillingComplete);
    }

    public function holdUntilBillingComplete(): bool
    {
        return $this->get(self::FIELD_HOLD_UNTIL_BILLING_COMPLETE);
    }

    /**
     * @return Traversable<int, Invoice>
     */
    public function getInvoices(): Traversable
    {
        /** @var Traversable<int, Invoice> */
        return $this->relations->getMany(self::LINK_INVOICES);
    }
}
