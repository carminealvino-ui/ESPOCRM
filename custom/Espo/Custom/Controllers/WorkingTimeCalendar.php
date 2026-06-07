<?php

namespace Espo\Custom\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Controllers\Record;

class WorkingTimeCalendar extends Record
{
    public function postActionGeneraDisponibilita(
        Request $request,
        Response $response
    ) {
        $data = $request->getParsedBody();
        $id = $data->id ?? null;

        if (!$id) {
            throw new \Exception('ID mancante');
        }

        $entityManager = $this->getContainer()->get('entityManager');

        $calendar = $entityManager->getEntityById('WorkingTimeCalendar', $id);

        if (!$calendar) {
            throw new \Exception('Calendario lavorativo non trovato');
        }

        $action = new \Espo\Custom\Actions\WorkingTimeCalendar\GeneraDisponibilita(
            $entityManager
        );

        return $action->run($calendar);
    }
}
