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

namespace Espo\Modules\Sales\Tools\Subscription\Control;

use Espo\Core\Field\Date;
use Espo\Modules\Sales\Entities\Subscription;
use Espo\Modules\Sales\Entities\SubscriptionBillingPlan as BillingPlan;
use Espo\Modules\Sales\Tools\Subscription\ConfigProvider;
use Espo\Modules\Sales\Tools\Subscription\IntervalUnit;
use Espo\Modules\Sales\Tools\Subscription\Util;
use LogicException;
use RuntimeException;

class BillingCycleHelper
{
    public function __construct(
        private ConfigProvider $configProvider,
    ) {}

    public function calculateDateEnd(BillingPlan $plan, Date $date, ?int $anchorDay): Date
    {
        $number = $plan->getIntervalNumber();
        $unit = $plan->getIntervalUnit();

        if ($unit === IntervalUnit::Year) {
            $unit = IntervalUnit::Month;
            $number *= 12;
        }

        $number *= $plan->getBillingCycleLength();

        if ($unit === IntervalUnit::Month) {
            return Util::addMonths($date, $number, $anchorDay, $this->configProvider->getMinMonthStepDays());
        }

        if ($unit === IntervalUnit::Day) {
            return $date->addDays($number);
        }

        if ($unit === IntervalUnit::Week) {
            return $date->addDays($number * 7);
        }

        /** @phpstan-ignore-next-line */
        throw new RuntimeException();
    }

    public function findAnchorDay(Date $date, Subscription $subscription): ?int
    {
        $plan = $subscription->getBillingPlan();

        if (
            $plan->getIntervalUnit() !== IntervalUnit::Month &&
            $plan->getIntervalUnit() !== IntervalUnit::Year
        ) {
            return null;
        }

        $anchorDay = $subscription->getAnchorDay();

        if ($plan->isAligningByDay()) {
            $alignmentDays = $plan->getAlignmentDays() ?: throw new LogicException("No alignment days.");

            if (Util::isAligned($date, $alignmentDays)) {
                // @todo Review.
                return Util::findClosestGreaterAlignmentDay($date->getDay(), $alignmentDays) ?? $date->getDay();
            }

            return null;
        }

        return $anchorDay;
    }
}
