<?php

namespace Espo\Custom\Tools\CrmKpi;

class Period
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

    public static function normalize(string $period): string
    {
        return self::isValid($period) ? $period : self::CURRENT_MONTH;
    }
}
