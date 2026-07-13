<?php

namespace Espo\Custom\Actions\WorkingTimeCalendar;

use Espo\Custom\Services\WorkingTimeCalendarDisponibilitaGenerator;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * v1 — generazione da dettaglio calendario (sostituita da Disponibilita/GeneraDisponibilitaRicorrenti).
 * Mantenuta per compatibilità API; usa utenti collegati al calendario.
 */
class GeneraDisponibilita
{
    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function run(Entity $calendar): object
    {
        $generator = new WorkingTimeCalendarDisponibilitaGenerator($this->entityManager);
        $result = $generator->generateFromCalendar($calendar);

        $dateFrom = substr((string) ($calendar->get('dataInizioGenerazione') ?? ''), 0, 10);
        $dateTo = substr((string) ($calendar->get('dataFineGenerazione') ?? ''), 0, 10);

        return (object) [
            'created' => $result['created'],
            'skipped' => $result['skipped'],
            'errors' => $result['errors'],
            'userCount' => $result['userCount'],
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'message' => sprintf(
                'Periodo %s → %s: create %d disponibilità per %d utenti, %d già presenti%s.',
                $dateFrom ?: '?',
                $dateTo ?: '?',
                $result['created'],
                $result['userCount'],
                $result['skipped'],
                $result['errors'] !== [] ? ', ' . count($result['errors']) . ' errori' : ''
            ),
        ];
    }
}
