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
use Espo\Core\Utils\DateTime;
use RuntimeException;

class Util
{
    public function __construct(
        private DateTime $dateTimeUtil,
    ) {}

    public function getToday(): Date
    {
        return Date::createToday($this->dateTimeUtil->getTimezone());
    }

    public static function addMonths(Date $date, int $months, ?int $anchorDay = null, int $minStepLength = 0): Date
    {
        $anchorDay ??= $date->getDay();

        $next = $date
            ->addDays(1 - $date->getDay())
            ->addMonths($months);

        $daysInMonth = self::getDaysInMonths($next);

        $day = min($anchorDay, $daysInMonth);

        $next = $next->addDays($day - 1);

        if ($date->diff($next)->days <= $minStepLength) {
            $next = self::addMonths($next, 1);
        }

        return $next;
    }

    /**
     * @param non-empty-array<int, int> $days
     */
    public static function findClosestAlignedDate(Date $date, array $days): Date
    {
        $closest = null;
        $minDiff = PHP_INT_MAX;
        $daysInMonth = self::getDaysInMonths($date);

        foreach ($days as $day) {
            $itDate = $date;

            if ($daysInMonth > $day) {
                $itDate = $itDate
                    ->addDays(1 - $date->getDay())
                    ->addDays($day - 1);

                if ($itDate->isLessThanOrEqualTo($date)) {
                    $itDate = self::addMonths($itDate, 1);
                }
            }

            $diff = (int) $date->diff($itDate)->days;

            if ($diff < $minDiff) {
                $minDiff = $diff;
                $closest = $itDate;
            }
        }

        if (!$closest) {
            throw new RuntimeException("Aligned date could not be found.");
        }

        return $closest;
    }

    /**
     * @param non-empty-array<int, int<0, 6>> $weekdays
     */
    public static function findClosestWeekdayAlignedDate(Date $date, array $weekdays): Date
    {
        $closest = null;
        $minDiff = PHP_INT_MAX;

        foreach ($weekdays as $day) {
            $p = $date;

            while ($p->getDayOfWeek() !== $day) {
                $p = $p->addDays(1);
            }

            $diff = (int) $date->diff($p)->days;

            if ($diff < $minDiff) {
                $minDiff = $diff;
                $closest = $p;
            }
        }

        if (!$closest) {
            throw new RuntimeException("Weekday aligned date could not be found.");
        }

        return $closest;
    }

    /**
     * @param int[] $days
     */
    static public function findClosestGreaterAlignmentDay(int $day, array $days): ?int
    {
        sort($days);

        foreach ($days as $it) {
            if ($it >= $day) {
                return $it;
            }
        }

        return null;
    }

    /**
     * @param int[] $days
     */
    static public function isAligned(Date $date, array $days): bool
    {
        if (in_array($date->getDay(), $days)) {
            return true;
        }

        $daysInMonth = self::getDaysInMonths($date);

        if ($date->getDay() === $daysInMonth) {
            foreach ($days as $day) {
                if ($day >= $date->getDay()) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param non-empty-array<int, int<0, 6>> $weekdays
     */
    static public function isAlignedByWeekday(Date $date, array $weekdays): bool
    {
        return self::findClosestWeekdayAlignedDate($date, $weekdays)->isEqualTo($date);
    }

    private static function getDaysInMonths(Date $itDate): int
    {
        return (int) $itDate->toDateTime()->format('t');
    }
}
