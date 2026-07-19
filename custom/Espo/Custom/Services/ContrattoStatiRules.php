<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;

/**
 * Coerenza Stato Contratto ↔ Stato Finanziamento.
 *
 * - Recesso ⇒ Stato Finanziamento = Annullato
 * - Chiuso + finanziamento (checkbox o stato valorizzato) ⇒ Approvato
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

        $hasFinancing = $finanziamento || $statoFinanziamento !== '';

        // Contanti senza finanziamento: nessuna forzatura.
        if (!$hasFinancing) {
            return;
        }

        if (!$finanziamento) {
            $entity->set('finanziamento', true);
        }

        if ($statoFinanziamento !== 'Approvato') {
            $entity->set('statoFinanziamento', 'Approvato');
        }
    }
}
