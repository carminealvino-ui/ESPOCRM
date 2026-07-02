<?php

namespace Espo\Custom\Classes\Select\Opportunity\PrimaryFilters;

use Espo\Core\Select\Primary\Filter;
use Espo\ORM\Query\SelectBuilder;

class ContrattiBacklog implements Filter
{
    /** @var string[] */
    private const FINANCING_BACKLOG_STATES = [
        'In lavorazione',
        'In rivalutazione',
        'In Attesa Documentazione',
        'Respinto',
    ];

    public function apply(SelectBuilder $queryBuilder): void
    {
        $queryBuilder->where([
            'stage' => 'Closed Won',
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
