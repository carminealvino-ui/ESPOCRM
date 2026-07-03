<?php

// =====================================================
// VERSIONE: 1.1.5
// DATA: 2026-07-03
// FILE: custom/Espo/Custom/Controllers/Opportunity.php
// =====================================================
//
// FIX 1.1.5 — EspoCRM 10: getContainer() rimosso, usa injectableFactory.
//
// =====================================================

namespace Espo\Custom\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Controllers\Record;
use Espo\Custom\Actions\Opportunity\CreateContratto;
use Espo\ORM\EntityManager;

class Opportunity extends Record
{
    public function postActionCreateContratto(
        Request $request,
        Response $response
    ) {
        $data = $request->getParsedBody();

        $id = $data->id ?? null;

        if (!$id) {
            throw new \Exception('ID mancante');
        }

        $entityManager = $this->injectableFactory->create(EntityManager::class);

        $opportunity = $entityManager->getEntityById('Opportunity', $id);

        if (!$opportunity) {
            throw new \Exception('Opportunità non trovata');
        }

        $action = new CreateContratto($entityManager);

        return $action->run($opportunity);
    }
}
