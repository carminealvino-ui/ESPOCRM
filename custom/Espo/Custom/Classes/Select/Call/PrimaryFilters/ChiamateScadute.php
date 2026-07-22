<?php

namespace Espo\Custom\Classes\Select\Call\PrimaryFilters;

use Espo\Core\Select\Primary\Filter;
use Espo\ORM\Query\SelectBuilder;

/**
 * Call Pianificato con dateStart nel passato (allineato a CrmKpi Alerts::countChiamateScadute).
 */
class ChiamateScadute implements Filter
{
    public function apply(SelectBuilder $queryBuilder): void
    {
        $queryBuilder->where([
            'status' => 'Planned',
            'dateStart<' => date('Y-m-d H:i:s'),
        ]);
    }
}
