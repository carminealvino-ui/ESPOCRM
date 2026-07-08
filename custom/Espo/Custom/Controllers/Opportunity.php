<?php

namespace Espo\Custom\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Controllers\Record;
use Espo\Core\InjectableFactory;

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

        $entityManager = $this->getContainer()->get('entityManager');
        $opportunity = $entityManager->getEntityById('Opportunity', $id);

        if (!$opportunity) {
            throw new \Exception('Opportunità non trovata');
        }

        /** @var InjectableFactory $injectableFactory */
        $injectableFactory = $this->getContainer()->get('injectableFactory');
        $action = $injectableFactory->create(\Espo\Custom\Actions\Opportunity\CreateContratto::class);

        return $action->run($opportunity);
    }
}
