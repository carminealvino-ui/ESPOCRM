<?php

namespace Espo\Custom\Classes\Select\Call\PrimaryFilters;

use Espo\Core\Select\Primary\Filter;
use Espo\ORM\Query\SelectBuilder;

class RichiamiPianificati implements Filter
{
    public function apply(SelectBuilder $queryBuilder): void
    {
        $queryBuilder->where([
            'status' => 'Planned',
            'AND' => [
                ['richiamo!=' => ''],
                ['richiamo!=' => null],
            ],
        ]);
    }
}
