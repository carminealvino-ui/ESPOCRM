<?php

namespace Espo\Custom\Classes\Select\Appuntamento\PrimaryFilters;

use Espo\Core\Select\Primary\Filter;
use Espo\Custom\Tools\CrmKpi\MonthRange;
use Espo\ORM\Query\SelectBuilder;

class AppuntamentiSenzaOpportunita implements Filter
{
    /** @var string[] */
    private const ESITI_ANNULLATI = [
        'Annullato dal Potenziale',
        'Annullato dal Consulente',
        'Annullato Azienda',
        'Annullato Call Center',
    ];

    public function apply(SelectBuilder $queryBuilder): void
    {
        [$from, $to] = MonthRange::bounds('currentMonth');

        $queryBuilder
            ->distinct()
            ->leftJoin('opportunita', 'oppSenzaAlert')
            ->where([
                'status' => 'Held',
                'sottostato!=' => self::ESITI_ANNULLATI,
                'oppSenzaAlert.id' => null,
            ]);

        if ($from !== null) {
            $queryBuilder->where(['dataAppuntamento>=' => $from]);
        }

        if ($to !== null) {
            $queryBuilder->where(['dataAppuntamento<=' => $to]);
        }
    }
}
