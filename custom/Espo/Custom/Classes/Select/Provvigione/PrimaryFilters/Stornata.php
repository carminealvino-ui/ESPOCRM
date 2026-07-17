<?php

namespace Espo\Custom\Classes\Select\Provvigione\PrimaryFilters;

use Espo\Core\Select\Primary\Filter;
use Espo\ORM\Query\SelectBuilder;

class Stornata implements Filter
{
    public function apply(SelectBuilder $queryBuilder): void
    {
        $queryBuilder->where([
            'statoProvvigione' => 'Stornata',
        ]);
    }
}
