<?php

namespace Espo\Custom\Actions\Quote;

use Espo\Core\Api\Request;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\NotFound;
use Espo\Custom\Services\ProvvigioneManager;
use Espo\ORM\EntityManager;
use stdClass;

/**
 * Calcola provvigioni consolidate sul contratto (modalità manuale o automatica).
 */
class CalcolaProvvigioni
{
    public function __construct(
        private EntityManager $entityManager,
        private ProvvigioneManager $provvigioneManager
    ) {}

    public function run(Request $request): stdClass
    {
        $data = $request->getParsedBody();
        $id = $data->id ?? null;

        if (!$id) {
            throw new BadRequest('id contratto obbligatorio.');
        }

        $quote = $this->entityManager->getEntityById('Quote', $id);

        if (!$quote) {
            throw new NotFound('Contratto non trovato.');
        }

        $opportunityId = $quote->get('opportunityId');

        if (!$opportunityId) {
            throw new BadRequest('Collegare il contratto a un\'opportunità prima del calcolo provvigioni.');
        }

        $opportunity = $this->entityManager->getEntityById('Opportunity', $opportunityId);

        if (!$opportunity) {
            throw new NotFound('Opportunità non trovata.');
        }

        $modalita = $quote->get('modalitaCalcoloProvvigioni') ?? 'Manuale';

        if ($modalita === 'Manuale') {
            $ruleIds = $quote->getLinkMultipleIdList('regoleProvvigionali') ?? [];

            if ($ruleIds === []) {
                throw new BadRequest(
                    'Selezionare almeno una Regola provvigionale sul contratto (modalità Manuale).'
                );
            }
        }

        $provvigioni = $this->provvigioneManager->createConsolidataForQuote($opportunity, $quote);

        $quote = $this->entityManager->getEntityById('Quote', $id);
        $totale = (float) ($quote?->get('totaleProvvigioni') ?? 0);

        return (object) [
            'success' => true,
            'count' => count($provvigioni),
            'totaleProvvigioni' => $totale,
            'provvigioneIds' => array_map(
                static fn ($p) => $p->getId(),
                $provvigioni
            ),
        ];
    }
}
