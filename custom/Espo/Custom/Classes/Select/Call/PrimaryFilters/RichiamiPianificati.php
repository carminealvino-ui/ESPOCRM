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
            'OR' => [
                'AND' => [
                    ['richiamo!=' => ''],
                    ['richiamo!=' => null],
                ],
                'tipologia' => [
                    'Richiamo su Opportunità Generata',
                    'Richiamo per Nuovo Appuntamento',
                    'Richiamo per Informazioni Aggiuntive',
                    'Contatto dopo Prima Visita',
                ],
            ],
        ]);
    }
}
