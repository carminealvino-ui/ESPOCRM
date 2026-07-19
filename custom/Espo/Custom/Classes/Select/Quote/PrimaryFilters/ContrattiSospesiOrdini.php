<?php

namespace Espo\Custom\Classes\Select\Quote\PrimaryFilters;

use Espo\Core\Select\Primary\Filter;
use Espo\ORM\Query\SelectBuilder;

/**
 * Contratti sospesi ordini: statoContratto Sospeso oppure senza Numero Contratto.
 */
class ContrattiSospesiOrdini implements Filter
{
    public function apply(SelectBuilder $queryBuilder): void
    {
        $queryBuilder->where([
            'OR' => [
                ['statoContratto' => 'Sospeso'],
                ['numeroContratto' => null],
                ['numeroContratto' => ''],
            ],
        ]);
    }
}
