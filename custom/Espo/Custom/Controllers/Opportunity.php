<?php

namespace Espo\Custom\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Controllers\Record;

/**
 * Controller base Opportunity.
 *
 * Necessario quando scope/metadata lato server risolve il controller
 * nel namespace Custom. Le action custom restano gestite da app/actions.
 */
class Opportunity extends Record
{
    public function postActionCreateContratto(
        Request $request,
        Response $response
    ): object {
        $data = $request->getParsedBody();
        $id = $data->id ?? null;

        if (!$id) {
            throw new \Exception('ID mancante');
        }

        $entityManager = $this->entityManager;
        $opportunity = $entityManager->getEntityById('Opportunity', $id);

        if (!$opportunity) {
            throw new \Exception('Opportunità non trovata');
        }

        $action = new \Espo\Custom\Actions\Opportunity\CreateContratto($entityManager);

        return $action->run($opportunity);
    }
}
