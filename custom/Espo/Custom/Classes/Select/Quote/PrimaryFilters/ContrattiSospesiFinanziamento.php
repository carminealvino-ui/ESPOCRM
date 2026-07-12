<?php

namespace Espo\Custom\Classes\Select\Quote\PrimaryFilters;

use Espo\Core\Select\Primary\Filter;
use Espo\ORM\Query\Part\Condition\Cond;

/**
 * Contratti con finanziamento in sospeso (allineato a CrmKpi Alerts).
 */
class ContrattiSospesiFinanziamento implements Filter
{
    public function apply(Cond $condition): Cond
    {
        return $condition->and(
            Cond::notEqual('statoContratto', 'Annullato'),
            Cond::notEqual('statoContratto', 'Recesso'),
            Cond::equal('finanziamento', true),
            Cond::or(
                Cond::equal('statoFinanziamento', 'In rivalutazione'),
                Cond::equal('statoFinanziamento', 'In Attesa Documentazione')
            )
        );
    }
}
