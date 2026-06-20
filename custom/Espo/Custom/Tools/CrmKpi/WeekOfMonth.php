<?php

namespace Espo\Custom\Tools\CrmKpi;

/**
 * Settimane nel mese: conta solo segmenti con >= 4 giorni nel mese (lun-dom).
 */
class WeekOfMonth
{
    const MIN_DAYS_IN_MONTH = 4;

    /**
     * @return array<int, array{index: int, start: string, end: string, label: string}>
     */
    public static function validWeeksInMonth($year, $month)
    {
        $year = (int) $year;
        $month = (int) $month;

        $monthStart = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $monthEnd = $monthStart->modify('last day of this month');

        $cursor = self::mondayOnOrBefore($monthStart);
        $weeks = [];
        $index = 0;

        while ($cursor <= $monthEnd) {
            $weekStart = $cursor;
            $weekEnd = $cursor->modify('+6 days');
            $segment = self::intersectRange($weekStart, $weekEnd, $monthStart, $monthEnd);

            if ($segment !== null && $segment['days'] >= self::MIN_DAYS_IN_MONTH) {
                $index++;
                $weeks[$index] = [
                    'index' => $index,
                    'start' => $segment['start'],
                    'end' => $segment['end'],
                    'label' => self::buildLabel($index, $segment['start'], $segment['end']),
                ];
            }

            $cursor = $cursor->modify('+7 days');
        }

        return $weeks;
    }

    /**
     * @return int|null Indice settimana valida (1-based) o null se settimana parziale (<4 gg)
     */
    public static function resolveIndexForDate($date)
    {
        if (!$date) {
            return null;
        }

        $parsed = new \DateTimeImmutable(substr((string) $date, 0, 10));
        $weeks = self::validWeeksInMonth(
            (int) $parsed->format('Y'),
            (int) $parsed->format('n')
        );

        $dateStr = $parsed->format('Y-m-d');

        foreach ($weeks as $week) {
            if ($dateStr >= $week['start'] && $dateStr <= $week['end']) {
                return $week['index'];
            }
        }

        return null;
    }

    /**
     * @param array<int, array{index: int, start: string, end: string, label: string}> $weeks
     * @return array<int, array{index: int, label: string, value: int, widthPercent: float}>
     */
    public static function buildChartRows(array $counts, array $weeks)
    {
        $rows = [];
        $max = 1;
        $total = 0;

        foreach ($counts as $value) {
            $total += (int) $value;
        }

        $totalBase = max($total, 1);

        foreach ($weeks as $index => $week) {
            $value = isset($counts[$index]) ? (int) $counts[$index] : 0;

            if ($value > $max) {
                $max = $value;
            }

            $rows[] = [
                'index' => $index,
                'label' => $week['label'],
                'value' => $value,
            ];
        }

        $result = [];

        foreach ($rows as $row) {
            $result[] = (object) [
                'index' => $row['index'],
                'label' => $row['label'],
                'value' => $row['value'],
                'widthPercent' => round(($row['value'] / $max) * 100, 1),
                'percentOfTotal' => round(($row['value'] / $totalBase) * 100, 1),
            ];
        }

        return $result;
    }

    /**
     * Etichette settimana per un periodo continuo (es. mese corrente).
     *
     * @return array<int, array{index: int, start: string, end: string, label: string}>
     */
    public static function validWeeksForRange($from, $to)
    {
        if (!$from || !$to) {
            return self::defaultWeekLabels();
        }

        $start = new \DateTimeImmutable($from);
        $end = new \DateTimeImmutable($to);
        $merged = [];

        $cursor = new \DateTimeImmutable($start->format('Y-m-01'));

        while ($cursor <= $end) {
            $weeks = self::validWeeksInMonth(
                (int) $cursor->format('Y'),
                (int) $cursor->format('n')
            );

            foreach ($weeks as $index => $week) {
                if ($week['end'] < $from || $week['start'] > $to) {
                    continue;
                }

                $merged[$index] = [
                    'index' => $index,
                    'start' => max($week['start'], $from),
                    'end' => min($week['end'], $to),
                    'label' => $week['label'],
                ];
            }

            $cursor = $cursor->modify('first day of next month');
        }

        if ($merged === []) {
            return self::defaultWeekLabels();
        }

        ksort($merged);

        return $merged;
    }

    /**
     * @return array<int, array{index: int, start: string, end: string, label: string}>
     */
    private static function defaultWeekLabels()
    {
        $weeks = [];

        for ($i = 1; $i <= 5; $i++) {
            $weeks[$i] = [
                'index' => $i,
                'start' => '',
                'end' => '',
                'label' => 'Sett. ' . $i,
            ];
        }

        return $weeks;
    }

    private static function mondayOnOrBefore(\DateTimeImmutable $date)
    {
        $weekday = (int) $date->format('N');

        if ($weekday === 1) {
            return $date;
        }

        return $date->modify('-' . ($weekday - 1) . ' days');
    }

    /**
     * @return array{start: string, end: string, days: int}|null
     */
    private static function intersectRange(
        \DateTimeImmutable $rangeStart,
        \DateTimeImmutable $rangeEnd,
        \DateTimeImmutable $boundStart,
        \DateTimeImmutable $boundEnd
    ) {
        $start = $rangeStart > $boundStart ? $rangeStart : $boundStart;
        $end = $rangeEnd < $boundEnd ? $rangeEnd : $boundEnd;

        if ($start > $end) {
            return null;
        }

        $days = (int) $start->diff($end)->days + 1;

        return [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'days' => $days,
        ];
    }

    private static function buildLabel($index, $start, $end)
    {
        $startDt = new \DateTimeImmutable($start);
        $endDt = new \DateTimeImmutable($end);
        $months = [
            1 => 'gen', 2 => 'feb', 3 => 'mar', 4 => 'apr', 5 => 'mag', 6 => 'giu',
            7 => 'lug', 8 => 'ago', 9 => 'set', 10 => 'ott', 11 => 'nov', 12 => 'dic',
        ];

        $startMonth = $months[(int) $startDt->format('n')] ?? $startDt->format('m');
        $endMonth = $months[(int) $endDt->format('n')] ?? $endDt->format('m');
        $startDay = (int) $startDt->format('j');
        $endDay = (int) $endDt->format('j');

        if ($startMonth === $endMonth) {
            $rangeLabel = $startDay . '-' . $endDay . ' ' . $startMonth;
        } else {
            $rangeLabel = $startDay . ' ' . $startMonth . '-' . $endDay . ' ' . $endMonth;
        }

        return 'Sett. ' . $index . ' (' . $rangeLabel . ')';
    }
}
