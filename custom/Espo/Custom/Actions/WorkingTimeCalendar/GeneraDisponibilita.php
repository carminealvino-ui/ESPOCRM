<?php

namespace Espo\Custom\Actions\WorkingTimeCalendar;

use Espo\Custom\Services\WorkingTimeCalendarDisponibilitaGenerator;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class GeneraDisponibilita
{
    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function run(Entity $calendar): object
    {
        $dateFrom = $calendar->get('dataInizioGenerazione');
        $dateTo = $calendar->get('dataFineGenerazione');
        $assignedUserIds = $calendar->getLinkMultipleIdList('generazioneAssignedUsers');
        $azienda = $calendar->get('generazioneAzienda');
        $status = $calendar->get('generazioneStatus') ?: 'Presente';

        if (!$dateFrom || !$dateTo) {
            throw new \Exception('Compilare Data inizio e Data fine generazione nel calendario.');
        }

        $generator = new WorkingTimeCalendarDisponibilitaGenerator($this->entityManager);

        $result = $generator->generate(
            $calendar,
            $dateFrom,
            $dateTo,
            $assignedUserIds,
            $azienda,
            $status
        );

        return (object) [
            'created' => $result['created'],
            'skipped' => $result['skipped'],
            'errors' => $result['errors'],
            'message' => sprintf(
                'Create %d disponibilità, %d già presenti%s.',
                $result['created'],
                $result['skipped'],
                $result['errors'] !== [] ? ', ' . count($result['errors']) . ' errori' : ''
            ),
        ];
    }
}
