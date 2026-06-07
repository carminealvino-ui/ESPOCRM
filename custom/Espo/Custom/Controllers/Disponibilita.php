<?php

namespace Espo\Custom\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;

class Disponibilita extends \Espo\Core\Templates\Controllers\Event
{
    public function postActionGeneraDisponibilitaRicorrenti(
        Request $request,
        Response $response
    ) {
        $data = $request->getParsedBody();

        if (!$data) {
            throw new \Exception('Dati mancanti');
        }

        $entityManager = $this->getContainer()->get('entityManager');

        $action = new \Espo\Custom\Actions\Disponibilita\GeneraDisponibilitaRicorrenti(
            $entityManager
        );

        return $action->run($data);
    }
}
