<?php

namespace Espo\Custom\Classes\Select\Provvigione\PrimaryFilters;

use Espo\Core\Select\Primary\Filter;
use Espo\ORM\Query\SelectBuilder;

class Consolidata implements Filter
{
    public function apply(SelectBuilder $queryBuilder): void
    {
        $queryBuilder->where([
            'statoProvvigione' => 'Consolidata',
        ]);
    }
}
