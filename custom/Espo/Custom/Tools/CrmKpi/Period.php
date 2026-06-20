<?php

namespace Espo\Custom\Tools\CrmKpi;

/**
 * Alias retrocompatibilità — la logica vive in DateRange.
 */
class Period
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
        return DateRange::isValid($period);
    }

    public static function normalize($period)
    {
        return DateRange::normalizePeriod($period);
    }
}
