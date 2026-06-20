<?php

namespace Espo\Custom\Tools\CrmKpi;

use Espo\Custom\Tools\DateTime\BusinessDateTime;

class DateRange
{
    public const TOTALS = 'totals';
    public const CURRENT_YEAR = 'currentYear';
    public const PREVIOUS_YEAR = 'previousYear';
    public const CURRENT_QUARTER = 'currentQuarter';
    public const PREVIOUS_QUARTER = 'previousQuarter';
    public const CURRENT_MONTH = 'currentMonth';
    public const PREVIOUS_MONTH = 'previousMonth';

    public const ALL = [
        self::TOTALS,
        self::CURRENT_YEAR,
        self::PREVIOUS_YEAR,
        self::CURRENT_QUARTER,
        self::PREVIOUS_QUARTER,
        self::CURRENT_MONTH,
        self::PREVIOUS_MONTH,
    ];

    public static function isValid(string $period): bool
    {
        return in_array($period, self::ALL, true);
    }

    public static function normalizePeriod(string $period): string
    {
        return self::isValid($period) ? $period : self::CURRENT_MONTH;
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: ?string, 3: ?string}
     */
    public static function resolve(string $period): array
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
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    private static function monthPair(\DateTimeImmutable $now, int $offset): array
    {
        $monthStart = $now->modify('first day of this month');

        if ($offset !== 0) {
            $monthStart = $monthStart->modify($offset . ' months');
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
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    private static function quarterPair(\DateTimeImmutable $now, int $offset): array
    {
        [$year, $quarter] = self::shiftQuarter(
            (int) $now->format('Y'),
            (int) ceil(((int) $now->format('n')) / 3),
            $offset
        );

        [$start, $end] = self::quarterBounds($year, $quarter, $now->getTimezone());
        [$prevYear, $prevQuarter] = self::shiftQuarter($year, $quarter, -1);
        [$prevStart, $prevEnd] = self::quarterBounds($prevYear, $prevQuarter, $now->getTimezone());

        return [
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
            $prevStart->format('Y-m-d'),
            $prevEnd->format('Y-m-d'),
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    private static function yearPair(\DateTimeImmutable $now, int $offset): array
    {
        $year = (int) $now->format('Y') + $offset;
        $prevYear = $year - 1;

        return [
            sprintf('%d-01-01', $year),
            sprintf('%d-12-31', $year),
            sprintf('%d-01-01', $prevYear),
            sprintf('%d-12-31', $prevYear),
        ];
    }

    /**
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     */
    private static function quarterBounds(int $year, int $quarter, \DateTimeZone $tz): array
    {
        $startMonth = ($quarter - 1) * 3 + 1;
        $start = new \DateTimeImmutable(sprintf('%d-%02d-01', $year, $startMonth), $tz);
        $endMonth = $startMonth + 2;
        $end = new \DateTimeImmutable(sprintf('%d-%02d-01', $year, $endMonth), $tz)
            ->modify('last day of this month');

        return [$start, $end];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private static function shiftQuarter(int $year, int $quarter, int $offset): array
    {
        $quarter += $offset;

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
