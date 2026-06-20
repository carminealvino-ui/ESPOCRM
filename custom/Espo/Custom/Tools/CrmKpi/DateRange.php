<?php

namespace Espo\Custom\Tools\CrmKpi;

use Espo\Custom\Tools\DateTime\BusinessDateTime;

class DateRange
{
    /**
     * @return array{0: ?string, 1: ?string, 2: ?string, 3: ?string}
     */
    public static function resolve(string $period): array
    {
        $period = Period::normalize($period);
        $tz = new \DateTimeZone(BusinessDateTime::BUSINESS_TIMEZONE);
        $now = new \DateTimeImmutable('now', $tz);

        if ($period === Period::TOTALS) {
            return [null, null, null, null];
        }

        return match ($period) {
            Period::CURRENT_MONTH => self::monthPair($now, 0),
            Period::PREVIOUS_MONTH => self::monthPair($now, -1),
            Period::CURRENT_QUARTER => self::quarterPair($now, 0),
            Period::PREVIOUS_QUARTER => self::quarterPair($now, -1),
            Period::CURRENT_YEAR => self::yearPair($now, 0),
            Period::PREVIOUS_YEAR => self::yearPair($now, -1),
            default => self::monthPair($now, 0),
        };
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    private static function monthPair(\DateTimeImmutable $now, int $offset): array
    {
        $monthStart = $now->modify('first day of this month')->modify($offset . ' months');
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
