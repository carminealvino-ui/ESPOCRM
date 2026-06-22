<?php

namespace Espo\Custom\Tools\CrmKpi;

class MonthRange
{
    /**
     * @return array{0: string, 1: string}
     */
    public static function bounds(string $period = 'currentMonth'): array
    {
        [$from, $to] = array_slice(DateRange::resolve($period), 0, 2);

        return [$from, $to];
    }
}
