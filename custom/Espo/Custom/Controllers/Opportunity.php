<?php

namespace Espo\Custom\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Controllers\Record;
use Espo\Core\InjectableFactory;
use Espo\Custom\Actions\Opportunity\CreateContratto;

class Opportunity extends Record
{
    public function postActionCreateContratto(Request $request, Response $response): object
    {
        $data = $request->getParsedBody();
        $id = $data->id ?? null;

        if (!$id) {
            throw new \RuntimeException('ID mancante.');
        }

        $entityManager = $this->getContainer()->get('entityManager');
        $opportunity = $entityManager->getEntityById('Opportunity', $id);

        if (!$opportunity) {
            throw new \RuntimeException('Opportunita non trovata.');
        }

        /** @var InjectableFactory $injectableFactory */
        $injectableFactory = $this->getContainer()->get('injectableFactory');

        return $injectableFactory
            ->create(CreateContratto::class)
            ->run($opportunity);
    }
}
