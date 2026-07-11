<?php

namespace Espo\Custom\Classes\Select\Appuntamento\PrimaryFilters;

use Espo\Core\Select\Primary\Filter;
use Espo\ORM\Query\SelectBuilder;

class Ingestibile implements Filter
{
    public function apply(SelectBuilder $queryBuilder): void
    {
        $queryBuilder->where([
            'status' => 'Ingestibile',
        ]);
    }
}
