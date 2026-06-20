<?php

namespace Espo\Custom\Tools\CrmKpi;

use Espo\Custom\Tools\DateTime\BusinessDateTime;

class DateRange
{
    const TOTALS = 'totals';
    const CURRENT_YEAR = 'currentYear';
    const PREVIOUS_YEAR = 'previousYear';
    const CURRENT_QUARTER = 'currentQuarter';
    const PREVIOUS_QUARTER = 'previousQuarter';
    const CURRENT_MONTH = 'currentMonth';
    const PREVIOUS_MONTH = 'previousMonth';

    const ALL = [
        self::TOTALS,
        self::CURRENT_YEAR,
        self::PREVIOUS_YEAR,
        self::CURRENT_QUARTER,
        self::PREVIOUS_QUARTER,
        self::CURRENT_MONTH,
        self::PREVIOUS_MONTH,
    ];

    public static function isValid($period)
    {
        return in_array($period, self::ALL, true);
    }

    public static function normalizePeriod($period)
    {
        return self::isValid($period) ? $period : self::CURRENT_MONTH;
    }

    /**
     * @return array
     */
    public static function resolve($period)
    {
        $period = self::normalizePeriod($period);
        $tz = new \DateTimeZone(BusinessDateTime::BUSINESS_TIMEZONE);
        $now = new \DateTimeImmutable('now', $tz);

        if ($period === self::TOTALS) {
            return [null, null, null, null];
        }

        if ($period === self::CURRENT_MONTH) {
            return self::monthPair($now, 0);
        }

        if ($period === self::PREVIOUS_MONTH) {
            return self::monthPair($now, -1);
        }

        if ($period === self::CURRENT_QUARTER) {
            return self::quarterPair($now, 0);
        }

        if ($period === self::PREVIOUS_QUARTER) {
            return self::quarterPair($now, -1);
        }

        if ($period === self::CURRENT_YEAR) {
            return self::yearPair($now, 0);
        }

        if ($period === self::PREVIOUS_YEAR) {
            return self::yearPair($now, -1);
        }

        return self::monthPair($now, 0);
    }

    /**
     * @return array
     */
    private static function monthPair(\DateTimeImmutable $now, $offset)
    {
        $monthStart = $now->modify('first day of this month');

        if ((int) $offset !== 0) {
            $monthStart = $monthStart->modify((int) $offset . ' months');
        }

        $monthEnd = $monthStart->modify('last day of this month');
        $prevStart = $monthStart->modify('-1 month')->modify('first day of this month');
        $prevEnd = $prevStart->modify('last day of this month');

        return [
            $monthStart->format('Y-m-d'),
            $monthEnd->format('Y-m-d'),
            $prevStart->format('Y-m-d'),
            $prevEnd->format('Y-m-d'),
        ];
    }

    /**
     * @return array
     */
    private static function quarterPair(\DateTimeImmutable $now, $offset)
    {
        $shifted = self::shiftQuarter(
            (int) $now->format('Y'),
            (int) ceil(((int) $now->format('n')) / 3),
            (int) $offset
        );
        $year = $shifted[0];
        $quarter = $shifted[1];

        $bounds = self::quarterBounds($year, $quarter, $now->getTimezone());
        $start = $bounds[0];
        $end = $bounds[1];

        $prevShifted = self::shiftQuarter($year, $quarter, -1);
        $prevBounds = self::quarterBounds($prevShifted[0], $prevShifted[1], $now->getTimezone());
        $prevStart = $prevBounds[0];
        $prevEnd = $prevBounds[1];

        return [
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
            $prevStart->format('Y-m-d'),
            $prevEnd->format('Y-m-d'),
        ];
    }

    /**
     * @return array
     */
    private static function yearPair(\DateTimeImmutable $now, $offset)
    {
        $year = (int) $now->format('Y') + (int) $offset;
        $prevYear = $year - 1;

        return [
            sprintf('%d-01-01', $year),
            sprintf('%d-12-31', $year),
            sprintf('%d-01-01', $prevYear),
            sprintf('%d-12-31', $prevYear),
        ];
    }

    /**
     * @return array
     */
    private static function quarterBounds($year, $quarter, \DateTimeZone $tz)
    {
        $startMonth = ($quarter - 1) * 3 + 1;
        $start = new \DateTimeImmutable(sprintf('%d-%02d-01', $year, $startMonth), $tz);
        $endMonth = $startMonth + 2;
        $end = new \DateTimeImmutable(sprintf('%d-%02d-01', $year, $endMonth), $tz);
        $end = $end->modify('last day of this month');

        return [$start, $end];
    }

    /**
     * @return array
     */
    private static function shiftQuarter($year, $quarter, $offset)
    {
        $year = (int) $year;
        $quarter = (int) $quarter;
        $quarter += (int) $offset;

        while ($quarter < 1) {
            $quarter += 4;
            $year--;
        }

        while ($quarter > 4) {
            $quarter -= 4;
            $year++;
        }

        return [$year, $quarter];
    }
}
