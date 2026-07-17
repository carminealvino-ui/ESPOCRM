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

        if (!$data) {
            throw new \Exception('Dati mancanti');
        }

        $action = $this->injectableFactory->create(GeneraDisponibilita::class);

        return $action->run($data);
    }
}
