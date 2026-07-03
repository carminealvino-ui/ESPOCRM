<?php

namespace Espo\Custom\Classes\Select\Quote\PrimaryFilters;

use Espo\Core\Select\Primary\Filter;
use Espo\ORM\Query\SelectBuilder;

class ContrattiSospesiFinanziamento implements Filter
{
    /** @var string[] */
    private const FINANCING_SUSPENDED_STATES = [
        'In rivalutazione',
        'In Attesa Documentazione',
    ];

    /** @var string[] */
    private const EXCLUDED_STATES = [
        'Annullato',
        'Recesso',
    ];

    public function apply(SelectBuilder $queryBuilder): void
    {
        $queryBuilder->where([
            'statoContratto!=' => self::EXCLUDED_STATES,
            'finanziamento' => true,
            'statoFinanziamento' => self::FINANCING_SUSPENDED_STATES,
        ]);
    }
}
