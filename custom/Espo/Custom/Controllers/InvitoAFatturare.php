<?php

namespace Espo\Custom\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Controllers\Record;
use Espo\Core\InjectableFactory;
use Espo\Custom\Actions\InvitoAFatturare\CollegaProvvigioni;
use Espo\Custom\Actions\InvitoAFatturare\GeneraDaProvvigioni;
use Espo\Custom\Actions\InvitoAFatturare\GetProvvigioniEleggibili;
use Espo\Custom\Services\InvitoAFatturareManager;
use Espo\ORM\EntityManager;

class InvitoAFatturare extends Record
{
    public function __construct(
        private InjectableFactory $injectableFactory,
        private EntityManager $entityManager
    ) {}

    public function postActionGeneraDaProvvigioni(Request $request, Response $response): object
    {
        return $this->injectableFactory
            ->create(GeneraDaProvvigioni::class)
            ->run($request);
    }

    public function postActionGetProvvigioniEleggibili(Request $request, Response $response): object
    {
        return $this->injectableFactory
            ->create(GetProvvigioniEleggibili::class)
            ->run($request);
    }

    public function getActionGetProvvigioniEleggibili(Request $request, Response $response): object
    {
        return $this->injectableFactory
            ->create(GetProvvigioniEleggibili::class)
            ->run($request);
    }

    public function postActionCollegaProvvigioni(Request $request, Response $response): object
    {
        return $this->injectableFactory
            ->create(CollegaProvvigioni::class)
            ->run($request);
    }

    public function postActionEmetti(Request $request, Response $response): object
    {
        $data = $request->getParsedBody();
        $id = $data->id ?? null;

        if (!$id) {
            throw new \Exception('ID invito mancante.');
        }

        $invito = $this->entityManager->getEntityById('InvitoAFatturare', $id);

        if (!$invito) {
            throw new \Exception('Invito non trovato.');
        }

        $manager = $this->injectableFactory->create(InvitoAFatturareManager::class);
        $manager->emettiInvito($invito);

        return (object) [
            'id' => $invito->getId(),
            'stato' => $invito->get('stato'),
        ];
    }
}
