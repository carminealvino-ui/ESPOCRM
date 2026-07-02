<?php

namespace Espo\Custom\Classes\Select\Opportunity\PrimaryFilters;

use Espo\Core\Select\Primary\Filter;
use Espo\Custom\Tools\CrmKpi\MonthRange;
use Espo\ORM\Query\SelectBuilder;

class InstallatoMesePrecedente implements Filter
{
    public function apply(SelectBuilder $queryBuilder): void
    {
        [$from, $to] = MonthRange::bounds('previousMonth');

        $where = [
            'stage' => 'Closed Won',
            'statoContratto' => 'Installato',
        ];

        if ($from !== null) {
            $where['installazione>='] = $from;
        }

        if ($to !== null) {
            $where['installazione<='] = $to;
        }

        $queryBuilder->where($where);
    }
}
