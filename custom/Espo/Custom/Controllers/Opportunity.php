<?php

// =====================================================
// VERSIONE: 1.1.4
// DATA: 11-05-2026
// FILE: custom/Espo/Custom/Controllers/Opportunity.php
// =====================================================
//
// FIX 1.1.4
// -----------------------------------------------------
// Passaggio corretto EntityManager
// alla action custom CreateContratto.
//
// =====================================================

namespace Espo\Custom\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\ORM\EntityManager;

class Opportunity
{
    public function __construct(
        private EntityManager $entityManager
    ) {}


    // =====================================================
    // ACTION CREATE CONTRATTO
    // =====================================================

    public function postActionCreateContratto(
        Request $request,
        Response $response
    ) {

        // =====================================================
        // BODY REQUEST
        // =====================================================

        $data = $request->getParsedBody();

        $id = $data->id ?? null;



        // =====================================================
        // VALIDAZIONE
        // =====================================================

        if (!$id) {
            throw new \Exception('ID mancante');
        }



        // =====================================================
        // ENTITY MANAGER
        // =====================================================

        $entityManager = $this->entityManager;



        // =====================================================
        // OPPORTUNITÀ
        // =====================================================

        $opportunity = $entityManager
            ->getEntityById('Opportunity', $id);

        if (!$opportunity) {
            throw new \Exception('Opportunità non trovata');
        }



        // =====================================================
        // ACTION CUSTOM
        // =====================================================

        $action = new \Espo\Custom\Actions\Opportunity\CreateContratto(
            $entityManager
        );

        $result = $action->run($opportunity);



        // =====================================================
        // RETURN
        // =====================================================

        return $result;
    }
}
