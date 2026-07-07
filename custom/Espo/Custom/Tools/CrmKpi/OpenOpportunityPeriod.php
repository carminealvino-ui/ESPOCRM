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

        $conditions = [
            ['stage!=' => 'Closed Won'],
            ['stage!=' => 'Closed Lost'],
        ];

        if ($from !== null) {
            $conditions[] = ['dataOpportunit>=' => $from];
        }

        if ($to !== null) {
            $conditions[] = ['dataOpportunit<=' => $to];
        }

        return [
            'AND' => $conditions,
        ];
    }
}
