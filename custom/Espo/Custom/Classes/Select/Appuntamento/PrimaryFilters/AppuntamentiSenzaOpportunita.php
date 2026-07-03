<?php

namespace Espo\Custom\Classes\Select\Appuntamento\PrimaryFilters;

use Espo\Core\Select\Primary\Filter;
use Espo\ORM\Query\SelectBuilder;

class AppuntamentiSenzaOpportunita implements Filter
{
    /** @var string[] */
    private const ESITI_ANNULLATI = [
        'Annullato dal Potenziale',
        'Annullato dal Consulente',
        'Annullato Azienda',
        'Annullato Call Center',
        'Appuntamento non in agenda',
    ];

    public function apply(SelectBuilder $queryBuilder): void
    {
        $queryBuilder
            ->distinct()
            ->leftJoin('opportunita', 'oppSenzaAlert')
            ->where([
                'status' => 'Held',
                'sottostato!=' => 'Annullato',
                'esito!=' => self::ESITI_ANNULLATI,
                'oppSenzaAlert.id' => null,
            ]);
    }
}
