<?php

namespace Espo\Custom\Classes\Select\Quote\PrimaryFilters;

use Espo\Core\Select\Primary\Filter;
use Espo\ORM\Query\SelectBuilder;

class ContrattiBacklog implements Filter
{
    /** @var string[] */
    private const FINANCING_BACKLOG_STATES = [
        'In rivalutazione',
        'In Attesa Documentazione',
        'Respinto',
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
            'OR' => [
                ['statoContratto' => 'Sospeso'],
                [
                    'AND' => [
                        ['finanziamento' => true],
                        ['statoFinanziamento' => self::FINANCING_BACKLOG_STATES],
                    ],
                ],
            ],
        ]);
    }
}
