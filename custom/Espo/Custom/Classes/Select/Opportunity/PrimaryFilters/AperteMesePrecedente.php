<?php

namespace Espo\Custom\Classes\Select\Opportunity\PrimaryFilters;

use Espo\Core\Select\Primary\Filter;
use Espo\Custom\Tools\CrmKpi\OpenOpportunityPeriod;
use Espo\ORM\Query\SelectBuilder;

class AperteMesePrecedente implements Filter
{
    public function apply(SelectBuilder $queryBuilder): void
    {
        $queryBuilder->where(OpenOpportunityPeriod::where('previousMonth'));
    }
}
