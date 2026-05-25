<?php

namespace Espo\Custom\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Controllers\Record;
use Espo\Core\InjectableFactory;

class InvitoAFatturare extends Record
{
    public function postActionGeneraDaProvvigioni(Request $request, Response $response): object
    {
        /** @var InjectableFactory $injectableFactory */
        $injectableFactory = $this->getContainer()->get('injectableFactory');

        return $injectableFactory
            ->create(\Espo\Custom\Actions\InvitoAFatturare\GeneraDaProvvigioni::class)
            ->run($request);
    }

    public function postActionEmetti(Request $request, Response $response): object
    {
        $data = $request->getParsedBody();
        $id = $data->id ?? null;

        if (!$id) {
            throw new \Exception('ID invito mancante.');
        }

        $entityManager = $this->getContainer()->get('entityManager');
        $invito = $entityManager->getEntityById('InvitoAFatturare', $id);

        if (!$invito) {
            throw new \Exception('Invito non trovato.');
        }

        $injectableFactory = $this->getContainer()->get('injectableFactory');
        $manager = $injectableFactory->create(\Espo\Custom\Services\InvitoAFatturareManager::class);
        $manager->emettiInvito($invito);

        return (object) [
            'id' => $invito->getId(),
            'stato' => $invito->get('stato'),
        ];
    }
}
