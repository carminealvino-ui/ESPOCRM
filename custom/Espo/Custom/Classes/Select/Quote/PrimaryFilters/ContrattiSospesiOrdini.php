<?php

namespace Espo\Custom\Classes\Select\Quote\PrimaryFilters;

use Espo\Core\Select\Primary\Filter;
use Espo\ORM\Query\Part\Condition\Cond;

class ContrattiSospesiOrdini implements Filter
{
    public function apply(Cond $condition): Cond
    {
        return $condition->and(
            Cond::equal('statoContratto', 'Sospeso')
        );
    }
}
