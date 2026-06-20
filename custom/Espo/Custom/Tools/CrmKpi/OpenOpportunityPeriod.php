<?php

namespace Espo\Custom\Tools\CrmKpi;

class OpenOpportunityPeriod
{
    /**
     * Opportunità non chiuse con data opportunità nel periodo selezionato.
     *
     * @return array<string, mixed>
     */
    public static function where(string $period): array
    {
        [$from, $to] = MonthRange::bounds($period);

        return [
            'AND' => [
                ['stage!=' => 'Closed Won'],
                ['stage!=' => 'Closed Lost'],
                ['dataOpportunit>=' => $from],
                ['dataOpportunit<=' => $to],
            ],
        ];
    }
}
