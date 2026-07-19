<?php

namespace Espo\Custom\Classes\Select\Quote\PrimaryFilters;

use Espo\Core\Select\Primary\Filter;
use Espo\ORM\Query\SelectBuilder;

/**
 * Contratti con finanziamento in sospeso (allineato a CrmKpi Alerts).
 */
class ContrattiSospesiFinanziamento implements Filter
{
    public function apply(SelectBuilder $queryBuilder): void
    {
        $queryBuilder->where([
            'statoContratto!=' => ['Annullato', 'Recesso'],
            'finanziamento' => true,
            'statoFinanziamento' => [
                'In rivalutazione',
                'In attesa documentazione',
            ],
        ]);
    }
}
