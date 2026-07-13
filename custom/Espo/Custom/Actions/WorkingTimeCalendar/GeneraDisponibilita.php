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
        $calendarId = $calendar->getId();

        if ($calendarId) {
            $fresh = $this->entityManager->getEntityById('WorkingTimeCalendar', $calendarId);

            if ($fresh) {
                $calendar = $fresh;
            }
        }

        $generator = new WorkingTimeCalendarDisponibilitaGenerator($this->entityManager);
        $result = $generator->generateFromCalendar($calendar);

        $dateFrom = substr((string) ($calendar->get('dataInizioGenerazione') ?? ''), 0, 10);
        $dateTo = substr((string) ($calendar->get('dataFineGenerazione') ?? ''), 0, 10);
        $diagnosis = $generator->diagnoseSlots($calendar, $dateFrom, $dateTo);

        return (object) [
            'created' => $result['created'],
            'skipped' => $result['skipped'],
            'errors' => $result['errors'],
            'userCount' => $result['userCount'],
            'daysBlocked' => $result['daysBlocked'] ?? 0,
            'daysWeekdayOff' => $result['daysWeekdayOff'] ?? 0,
            'daysNoSlots' => $result['daysNoSlots'] ?? 0,
            'daysWithSlots' => $result['daysWithSlots'] ?? 0,
            'blockingExceptions' => $diagnosis['blockingExceptions'],
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'message' => $generator->formatGenerationMessage($result, $dateFrom, $dateTo),
        ];
    }
}
