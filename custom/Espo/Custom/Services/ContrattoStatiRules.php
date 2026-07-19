<?php

namespace Espo\Custom\Services;

use Espo\Core\Exceptions\Error;
use Espo\ORM\Entity;

/**
 * Coerenza Stato Contratto ↔ Stato Finanziamento.
 *
 * - Recesso ⇒ Stato Finanziamento = Annullato
 *   (anche se c'era una richiesta di finanziamento)
 * - Chiuso consentito solo se c'è finanziamento e stato = Approvato
 */
class ContrattoStatiRules
{
    public function apply(Entity $entity): void
    {
        $entityType = $entity->getEntityType();

        if ($entityType !== 'Quote' && $entityType !== 'Opportunity') {
            return;
        }

        $statoContratto = trim((string) ($entity->get('statoContratto') ?? ''));
        $statoFinanziamento = trim((string) ($entity->get('statoFinanziamento') ?? ''));
        $finanziamento = (bool) $entity->get('finanziamento');

        if ($statoContratto === 'Recesso') {
            if ($statoFinanziamento !== 'Annullato') {
                $entity->set('statoFinanziamento', 'Annullato');
            }

            return;
        }

        if ($statoContratto !== 'Chiuso') {
            return;
        }

        $hasFinancingRequest = $finanziamento || $statoFinanziamento !== '';

        // Contanti senza richiesta finanziamento: Chiuso consentito.
        if (!$hasFinancingRequest) {
            return;
        }

        if ($statoFinanziamento === 'Approvato' && $finanziamento) {
            return;
        }

        // C'è (o c'era) una richiesta: Chiuso solo con Approvato.
        if ($statoFinanziamento !== 'Approvato') {
            throw new Error(
                'Stato Contratto "Chiuso" consentito solo se c\'è finanziamento '
                . 'e Stato Finanziamento è "Approvato".'
            );
        }

        // Approvato ma checkbox finanziamento spento → riattivalo.
        if (!$finanziamento) {
            $entity->set('finanziamento', true);
        }
    }
}
