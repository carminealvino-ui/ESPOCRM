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

namespace Espo\Modules\Sales\Tools\Subscription;

use Espo\Core\Field\Date;
use Espo\Modules\Sales\Entities\Subscription;
use Espo\Modules\Sales\Entities\SubscriptionBillingPlan as BillingPlan;
use Espo\Modules\Sales\Entities\SubscriptionPeriod as Period;
use Espo\Modules\Sales\Tools\Subscription\Control\BillingCycleHelper;
use Espo\ORM\EntityManager;
use LogicException;

class CreateUpcomingPeriod
{
    public function __construct(
        private EntityManager $entityManager,
        private Util $util,
        private SubscriptionRepository $subscriptionRepository,
        private PeriodRepository $periodRepository,
        private BillingCycleHelper $billingCycleHelper,
    ) {}

    public function process(PointerData $pointerData): void
    {
        $this->subscriptionRepository->refreshAndLock($pointerData->getSubscription());

        $stubPeriod = $this->toAlignImmediate($pointerData) ?
            $this->prepareStubPeriod($pointerData) : null;

        if ($stubPeriod) {
            $pointerData = $pointerData->withDate($stubPeriod->getEndDate());
        }

        $nextPeriod = $this->prepareNextPeriod($pointerData);

        if ($nextPeriod) {
            $pointerData = $pointerData->withDate($nextPeriod->getEndDate());
        }

        $delayedStubPeriod = $nextPeriod && $this->toAlignDelayed($pointerData) ?
            $this->prepareStubPeriod($pointerData) : null;

        $this->savePeriods($stubPeriod, $nextPeriod, $delayedStubPeriod);

        if (!$stubPeriod && !$delayedStubPeriod && $nextPeriod) {
            $this->controlAnchorDay($nextPeriod);
        }
    }

    /**
     * @return array{Date, Date}
     */
    private function findNextPeriodRange(PointerData $pointerData): array
    {
        $start = $pointerData->getDate();

        $anchorDay = $this->billingCycleHelper->findAnchorDay($start, $pointerData->getSubscription());

        $end = $this->billingCycleHelper->calculateDateEnd($pointerData->getPlan(), $start, $anchorDay);

        $limitDate = $pointerData->getSubscription()->getEndDate();

        if ($limitDate && $end->isGreaterThan($limitDate)) {
            $end = $limitDate;
        }

        return [$start, $end];
    }

    private function prepareNextPeriod(PointerData $pointerData): ?Period
    {
        [$start, $end] = $this->findNextPeriodRange($pointerData);

        return $start->isLessThan($end) ?
            $this->preparePeriod(
                startDate: $start,
                endDate: $end,
                subscription: $pointerData->getSubscription(),
                holdUntilBillingComplete: $pointerData->holdUntilBillingComplete(),
            ) : null;
    }

    private function preparePeriod(
        Date $startDate,
        Date $endDate,
        Subscription $subscription,
        bool $charge = true,
        bool $holdUntilBillingComplete = false,
    ): Period {

        $nextPeriod = $this->periodRepository->getNew();

        $nextPeriod
            ->setType(Period::TYPE_REGULAR)
            ->setBillingStatus(
                $charge ?
                    Period::BILLING_STATUS_PENDING :
                    Period::BILLING_STATUS_SETTLED
            )
            ->setInvoiceAutomatically(true)
            ->setStatus(
                $startDate->isLessThanOrEqualTo($this->util->getToday()) ?
                    Period::STATUS_ACTIVE :
                    Period::STATUS_SCHEDULED
            )
            ->setSubscription($subscription)
            ->setStartDate($startDate)
            ->setHoldUntilBillingComplete($holdUntilBillingComplete)
            ->setEndDate($endDate);

        return $nextPeriod;
    }

    private function isProrationRangeCharged(BillingPlan $plan, Date $start, Date $end): bool
    {
        return $plan->getAlignmentProrationPolicy() === AlignmentProrationPolicy::Charge &&
        (
            $plan->getAlignmentChargeMinDays() === null ||
            $plan->getAlignmentChargeMinDays() <= $start->diff($end)->days
        );
    }

    private function prepareStubPeriod(PointerData $pointerData): ?Period
    {
        $plan = $pointerData->getPlan();

        $limitDate = $pointerData->getSubscription()->getEndDate();
        $start = $pointerData->getDate();

        if ($plan->isAligningByDay()) {
            $alignmentDays = $plan->getAlignmentDays() ?: throw new LogicException();

            $end = Util::findClosestAlignedDate($start, $alignmentDays);
        } else if ($plan->isAligningByWeekday()) {
            $alignmentWeekdays = $plan->getAlignmentWeekdays() ?: throw new LogicException();

            $end = Util::findClosestWeekdayAlignedDate($start, $alignmentWeekdays);
        } else {
            return null;
        }

        if ($limitDate && $end->isGreaterThan($limitDate)) {
            $end = $limitDate;
        }

        if ($start->isGreaterThanOrEqualTo($end)) {
            return null;
        }

        return $this->preparePeriod(
            startDate: $start,
            endDate: $end,
            subscription: $pointerData->getSubscription(),
            charge: $this->isProrationRangeCharged($plan, $start, $end),
            holdUntilBillingComplete: $pointerData->holdUntilBillingComplete(),
        );
    }

    private function toAlignByDayOfMonth(PointerData $pointerData): bool
    {
        $plan = $pointerData->getPlan();

        return $plan->isAligningByDay() &&
            !Util::isAligned($pointerData->getDate(), $plan->getAlignmentDays());
    }

    private function toAlignByWeekday(PointerData $pointerData): bool
    {
        $plan = $pointerData->getPlan();

        if (!$plan->isAligningByWeekday()) {
            return false;
        }

        $weekdays = $plan->getAlignmentWeekdays() ?: throw new LogicException();

        return !Util::isAlignedByWeekday($pointerData->getDate(), $weekdays);
    }

    private function toAlignImmediate(PointerData $pointerData): bool
    {
        if ($pointerData->getPlan()->getAlignmentType() !== AlignmentType::Immediate) {
            return false;
        }

        return $this->toAlignByDayOfMonth($pointerData) || $this->toAlignByWeekday($pointerData);
    }

    private function toAlignDelayed(PointerData $pointerData): bool
    {
        if ($pointerData->getPlan()->getAlignmentType() !== AlignmentType::Delayed) {
            return false;
        }

        return $this->toAlignByDayOfMonth($pointerData) || $this->toAlignByWeekday($pointerData);
    }

    private function savePeriods(?Period $stubPeriod, ?Period $nextPeriod, ?Period $delayedStubPeriod): void
    {
        if ($stubPeriod) {
            $this->entityManager->saveEntity($stubPeriod);
        }

        if ($nextPeriod) {
            $this->entityManager->saveEntity($nextPeriod);
        }

        if ($delayedStubPeriod) {
            $this->entityManager->saveEntity($delayedStubPeriod);
        }
    }

    private function controlAnchorDay(Period $period): void
    {
        $subscription = $period->getSubscription();

        $unit = $subscription->getBillingPlan()->getIntervalUnit();

        if (
            $subscription->getAnchorDay() !== null ||
            (
                $unit !== IntervalUnit::Month &&
                $unit !== IntervalUnit::Year
            )
        ) {
            return;
        }

        $subscription->setAnchorDay($period->getStartDate()->getDay());

        $this->entityManager->saveEntity($subscription);
    }
}
