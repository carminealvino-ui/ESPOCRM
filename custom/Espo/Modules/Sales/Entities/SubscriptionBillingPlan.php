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

use Espo\Core\ORM\Entity;
use Espo\Modules\Sales\Tools\Subscription\AlignmentProrationPolicy;
use Espo\Modules\Sales\Tools\Subscription\AlignmentType;
use Espo\Modules\Sales\Tools\Subscription\IntervalUnit;
use UnexpectedValueException;

class SubscriptionBillingPlan extends Entity
{
    public const ENTITY_TYPE = 'SubscriptionBillingPlan';

    public const STATUS_ACTIVE = 'Active';
    public const STATUS_INACTIVE = 'Inactive';

    public const FIELD_STATUS = 'status';
    public const FIELD_ALIGNMENT_DAYS = 'alignmentDays';
    public const FIELD_ALIGNMENT_WEEKDAYS = 'alignmentWeekdays';


    protected function setInContainer(string $attribute, mixed $value): void
    {
        parent::setInContainer($attribute, $value);

        if ($attribute === 'interval') {
            $unit = null;

            if (is_string($value)) {
                $unit = $value[strlen($value) - 1] ?? null;

                $this->set('intervalUnit', $unit);
            }

            parent::setInContainer('intervalUnit', $unit);
        }
    }

    public function getName(): ?string
    {
        return $this->get('name');
    }

    public function getStatus(): string
    {
        return $this->get('status');
    }

    public function getInterval(): string
    {
        return $this->get('interval');
    }

    public function getIntervalNumber(): int
    {
        $interval = $this->getInterval();

        $interval = substr($interval, 0, -1);

        return (int) $interval;
    }

    public function getIntervalUnit(): IntervalUnit
    {
        $interval = $this->getInterval();

        $unit = $interval[strlen($interval) - 1] ?? null;

        if (!$unit) {
            throw new UnexpectedValueException("Bad interval.");
        }

        return IntervalUnit::from($unit);
    }

    private function isIntervalUnitMonthOrYear(): bool
    {
        return $this->getIntervalUnit() === IntervalUnit::Month ||
            $this->getIntervalUnit() === IntervalUnit::Year;
    }

    public function isIntervalUnitWeek(): bool
    {
        return $this->getIntervalUnit() === IntervalUnit::Week;
    }

    public function setInterval(string $interval): self
    {
        return $this->set('interval', $interval);
    }

    public function getBillingCycleLength(): int
    {
        return $this->get('billingCycleLength');
    }

    public function setBillingCycleLength(string $length): self
    {
        return $this->set('billingCycleLength', $length);
    }

    public function getGracePeriodDays(): int
    {
        return $this->get('gracePeriodDays');
    }

    public function setGracePeriodDays(int $days): self
    {
        return $this->set('gracePeriodDays', $days);
    }

    public function getInvoiceDuePeriodDays(): int
    {
        return $this->get('invoiceDuePeriodDays');
    }

    public function setInvoiceDuePeriodDays(int $days): self
    {
        return $this->set('invoiceDuePeriodDays', $days);
    }

    public function getInvoiceLeadTimeDays(): int
    {
        return $this->get('invoiceLeadTimeDays');
    }

    public function setInvoiceLeadTimeDays(int $days): self
    {
        return $this->set('invoiceLeadTimeDays', $days);
    }

    /**
     * @return array<int, int<1, 31>>
     */
    public function getAlignmentDays(): array
    {
        $days = $this->get('alignmentDays') ?? [];

        foreach ($days as $day) {
            if (!is_int($day)) {
                throw new UnexpectedValueException("Non-int item in alignmentDays.");
            }
        }

        return $days;
    }

    /**
     * @return array<int, int<0, 6>>
     */
    public function getAlignmentWeekdays(): array
    {
        $days = $this->get('alignmentWeekdays') ?? [];

        foreach ($days as $day) {
            if (!is_int($day)) {
                throw new UnexpectedValueException("Non-int item in alignmentWeekdays.");
            }

            if ($day < 0 || $day > 6) {
                throw new UnexpectedValueException("Bad item number in alignmentWeekdays.");
            }
        }

        return $days;
    }

    /**
     * @param array<int, int<1, 31>> $days
     */
    public function setAlignmentDays(array $days): self
    {
        return $this->set(self::FIELD_ALIGNMENT_DAYS, $days);
    }

    /**
     * @param array<int, int<0, 6>> $days
     */
    public function setAlignmentWeekdays(array $days): self
    {
        return $this->set(self::FIELD_ALIGNMENT_WEEKDAYS, $days);
    }

    public function getAlignmentType(): ?AlignmentType
    {
        $raw = $this->get('alignmentType');

        if (!$raw) {
            return null;
        }

        return AlignmentType::from($raw);
    }

    public function setAlignmentType(?AlignmentType $type): self
    {
        return $this->set('alignmentType', $type);
    }

    public function setAlignmentProrationPolicy(?AlignmentProrationPolicy $policy): self
    {
        return $this->set('alignmentProrationPolicy', $policy);
    }

    public function getAlignmentProrationPolicy(): ?AlignmentProrationPolicy
    {
        $raw = $this->get('alignmentProrationPolicy');

        if (!$raw) {
            return null;
        }

        return AlignmentProrationPolicy::from($raw);
    }

    public function getAlignmentChargeMinDays(): ?int
    {
        return $this->get('alignmentChargeMinDays');
    }

    public function setAlignmentChargeMinDays(?int $days): self
    {
        return $this->set('alignmentChargeMinDays', $days);
    }

    public function isAligningByDay(): bool
    {
        return $this->getAlignmentType() &&
            $this->isIntervalUnitMonthOrYear() &&
            $this->getAlignmentDays();
    }

    public function isAligningByWeekday(): bool
    {
        return $this->getAlignmentType() &&
            $this->isIntervalUnitWeek() &&
            $this->getAlignmentWeekdays();
    }

    public function createPaymentRequests(): bool
    {
        return $this->get('createPaymentRequests');
    }

    public function sendPaymentRequests(): bool
    {
        return $this->get('sendPaymentRequests');
    }

    public function sendInvoices(): bool
    {
        return $this->get('sendInvoices');
    }
}
