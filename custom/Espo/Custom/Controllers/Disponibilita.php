<?php

namespace Espo\Custom\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Controllers\Record;
use Espo\Custom\Actions\Disponibilita\BackfillBrandColorCalendario;
use Espo\Custom\Actions\Disponibilita\GeneraDisponibilitaRicorrenti;

/**
 * Disponibilità: azioni custom (Espo 10, senza getContainer).
 */
class Disponibilita extends Record
{
    public function postActionGeneraDisponibilitaRicorrenti(
        Request $request,
        Response $response
    ): object {
        $data = $request->getParsedBody();

        if (!$data) {
            throw new \Exception('Dati mancanti');
        }

        $action = $this->injectableFactory->create(GeneraDisponibilitaRicorrenti::class);

        return $action->run($data);
    }

    public function postActionBackfillBrandColorCalendario(
        Request $request,
        Response $response
    ): object {
        $data = $request->getParsedBody() ?? (object) [];

        $action = $this->injectableFactory->create(BackfillBrandColorCalendario::class);

        return $action->run($data);
    }
}
