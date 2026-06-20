<?php

namespace Espo\Custom\Tools\CrmKpi;

/**
 * Alias retrocompatibilità — la logica vive in DateRange.
 */
class Period
{
    public const TOTALS = DateRange::TOTALS;
    public const CURRENT_YEAR = DateRange::CURRENT_YEAR;
    public const PREVIOUS_YEAR = DateRange::PREVIOUS_YEAR;
    public const CURRENT_QUARTER = DateRange::CURRENT_QUARTER;
    public const PREVIOUS_QUARTER = DateRange::PREVIOUS_QUARTER;
    public const CURRENT_MONTH = DateRange::CURRENT_MONTH;
    public const PREVIOUS_MONTH = DateRange::PREVIOUS_MONTH;
    public const ALL = DateRange::ALL;

    public static function isValid(string $period): bool
    {
        return DateRange::isValid($period);
    }

    public static function normalize(string $period): string
    {
        return DateRange::normalizePeriod($period);
    }
}
