<?php

namespace Espo\Custom\Actions\Disponibilita;

use Espo\Custom\Services\WorkingTimeCalendarDisponibilitaGenerator;
use Espo\ORM\EntityManager;

class GeneraDisponibilitaRicorrenti
{
    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function run(object $data): object
    {
        $calendarId = $data->calendarId ?? null;

        if (!$calendarId) {
            throw new \Exception('Selezionare un calendario lavorativo.');
        }

        $calendar = $this->entityManager->getEntityById('WorkingTimeCalendar', $calendarId);

        if (!$calendar) {
            throw new \Exception('Calendario lavorativo non trovato.');
        }

        $patch = [];

        foreach ([
            'dataInizioGenerazione',
            'dataFineGenerazione',
            'generazioneProductBrandId',
            'generazioneProductBrandName',
            'generazioneStatus',
            'generazioneArea',
        ] as $field) {
            if (isset($data->$field)) {
                $patch[$field] = $data->$field;
            }
        }

        if (isset($data->generazioneCollaboratorsIds)) {
            $patch['generazioneCollaboratorsIds'] = $data->generazioneCollaboratorsIds;
        }

        if ($patch !== []) {
            $calendar->set($patch);
            $this->entityManager->saveEntity($calendar, [
                'skipAutoGeneraDisponibilita' => true,
            ]);
        }

        $generator = new WorkingTimeCalendarDisponibilitaGenerator($this->entityManager);
        $result = $generator->generateFromCalendar($calendar);

        $dateFrom = substr((string) ($calendar->get('dataInizioGenerazione') ?? ''), 0, 10);
        $dateTo = substr((string) ($calendar->get('dataFineGenerazione') ?? ''), 0, 10);

        return (object) [
            'created' => $result['created'],
            'skipped' => $result['skipped'],
            'errors' => $result['errors'],
            'userCount' => $result['userCount'],
            'daysBlocked' => $result['daysBlocked'] ?? 0,
            'daysNoSlots' => $result['daysNoSlots'] ?? 0,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'message' => $generator->formatGenerationMessage($result, $dateFrom, $dateTo),
        ];
    }
}
