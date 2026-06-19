<?php

namespace Espo\Custom\Tools\CrmKpi;

use Espo\Custom\Tools\DateTime\BusinessDateTime;

class DateRange
{
    /**
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    public static function resolve(string $period): array
    {
        $tz = new \DateTimeZone(BusinessDateTime::BUSINESS_TIMEZONE);
        $now = new \DateTimeImmutable('now', $tz);

        if ($period === 'previousMonth') {
            $start = $now->modify('first day of last month')->setTime(0, 0, 0);
            $end = $now->modify('last day of last month')->setTime(23, 59, 59);
            $prevStart = $start->modify('first day of last month');
            $prevEnd = $start->modify('last day of last month')->setTime(23, 59, 59);

            return [
                $start->format('Y-m-d'),
                $end->format('Y-m-d'),
                $prevStart->format('Y-m-d'),
                $prevEnd->format('Y-m-d'),
            ];
        }

        $start = $now->modify('first day of this month')->setTime(0, 0, 0);
        $end = $now->modify('last day of this month')->setTime(23, 59, 59);
        $prevStart = $start->modify('first day of last month');
        $prevEnd = $start->modify('last day of last month')->setTime(23, 59, 59);

        return [
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
            $prevStart->format('Y-m-d'),
            $prevEnd->format('Y-m-d'),
        ];
    }
}
