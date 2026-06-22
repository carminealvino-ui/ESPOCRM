<?php

namespace Espo\Custom\Classes\Select\Quote\PrimaryFilters;

use Espo\Core\Select\Primary\Filter;
use Espo\Custom\Tools\CrmKpi\MonthRange;
use Espo\ORM\Query\SelectBuilder;

class DataInstallazionePeriodo implements Filter
{
    public function apply(SelectBuilder $queryBuilder): void
    {
        [$from, $to] = MonthRange::bounds('currentMonth');

        $conditions = [
            ['dataInstallazione!=' => null],
            ['dataInstallazione!=' => ''],
        ];

        if ($from !== null) {
            $conditions[] = ['dataInstallazione>=' => $from];
        }

        if ($to !== null) {
            $conditions[] = ['dataInstallazione<=' => $to];
        }

        $queryBuilder->where([
            'AND' => $conditions,
        ]);
    }
}
