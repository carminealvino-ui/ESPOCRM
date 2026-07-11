<?php

namespace Espo\Custom\Classes\Select\Provvigione\PrimaryFilters;

use Espo\Core\Select\Primary\Filter;
use Espo\ORM\Query\Part\Condition\Cond;

class Forecast implements Filter
{
    public function apply(Cond $condition): Cond
    {
        return $condition->and(
            Cond::equal('statoProvvigione', 'Forecast')
        );
    }
}
