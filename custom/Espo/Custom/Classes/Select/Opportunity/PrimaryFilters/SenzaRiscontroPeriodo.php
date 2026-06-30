<?php

namespace Espo\Custom\Classes\Select\Opportunity\PrimaryFilters;

use Espo\Core\Select\Primary\Filter;
use Espo\Custom\Tools\CrmKpi\MonthRange;
use Espo\ORM\Query\SelectBuilder;

class SenzaRiscontroPeriodo implements Filter
{
    public function apply(SelectBuilder $queryBuilder): void
    {
        [$from, $to] = MonthRange::bounds('currentMonth');

        $queryBuilder->leftJoin('appuntamento');

        $conditions = [
            ['stage!=' => 'Closed Won'],
            ['stage!=' => 'Closed Lost'],
            ['appuntamentoId!=' => null],
            ['appuntamentoId!=' => ''],
        ];

        if ($from !== null) {
            $conditions[] = ['appuntamento.dataAppuntamento>=' => $from];
        }

        if ($to !== null) {
            $conditions[] = ['appuntamento.dataAppuntamento<=' => $to];
        }

        $queryBuilder->where([
            'AND' => $conditions,
        ]);
    }
}
