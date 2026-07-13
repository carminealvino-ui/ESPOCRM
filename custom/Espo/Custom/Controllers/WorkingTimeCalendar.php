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

        $entityManager = $this->entityManager;
        $calendar = $entityManager->getEntityById('WorkingTimeCalendar', $id);

        if (!$calendar) {
            throw new \Exception('Calendario lavorativo non trovato');
        }

        $action = $this->injectableFactory->create(GeneraDisponibilita::class);

        return $action->run($calendar);
    }
}
