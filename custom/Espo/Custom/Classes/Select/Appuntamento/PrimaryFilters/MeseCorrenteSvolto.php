<?php

namespace Espo\Custom\Classes\Select\Appuntamento\PrimaryFilters;

use Espo\Core\Select\Primary\Filter;
use Espo\Custom\Tools\CrmKpi\MonthRange;
use Espo\ORM\Query\SelectBuilder;

class MeseCorrenteSvolto implements Filter
{
    public function apply(SelectBuilder $queryBuilder): void
    {
        [$from, $to] = MonthRange::bounds('currentMonth');

        $queryBuilder->where([
            'status' => 'Held',
            'dataAppuntamento>=' => $from,
            'dataAppuntamento<=' => $to,
        ]);
    }
}
