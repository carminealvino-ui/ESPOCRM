<?php

namespace Espo\Custom\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Controllers\Record;
use Espo\Custom\Actions\WorkingTimeCalendar\GeneraDisponibilita;

class WorkingTimeCalendar extends Record
{
    public function postActionGeneraDisponibilita(
        Request $request,
        Response $response
    ): object {
        $data = $request->getParsedBody();
        $id = $data->id ?? null;

        if (!$id) {
            throw new \Exception('ID mancante');
        }

        $calendar = $this->entityManager->getEntityById('WorkingTimeCalendar', $id);

        if (!$calendar) {
            throw new \Exception('Calendario lavorativo non trovato');
        }

        $patch = $this->extractGenerationPatch($data);

        if ($patch !== []) {
            $calendar->set($patch);
            $this->entityManager->saveEntity($calendar, [
                'skipAutoGeneraDisponibilita' => true,
            ]);
        }

        $action = $this->injectableFactory->create(GeneraDisponibilita::class);

        return $action->run($calendar);
    }

  /**
   * @return array<string, mixed>
   */
    private function extractGenerationPatch(object $data): array
    {
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

        return $patch;
    }
}
