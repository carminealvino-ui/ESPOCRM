<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;

/**
 * Coerenza Stato Contratto ↔ Stato Finanziamento.
 *
 * - Recesso ⇒ Stato Finanziamento = Annullato
 * - Chiuso + finanziamento ⇒ Approvato
 * - Normalizza valori obsoleti enum finanziamento
 */
class ContrattoStatiRules
{
    /** @var array<string, string> */
    private const FINANZIAMENTO_ALIASES = [
        'In Attesa Documentazione' => 'In attesa documentazione',
        'In lavorazione' => 'In valutazione',
    ];

    public function apply(Entity $entity): void
    {
        $entityType = $entity->getEntityType();

        if ($entityType !== 'Quote' && $entityType !== 'Opportunity') {
            return;
        }

        $this->normalizeStatoFinanziamento($entity);

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

    private function normalizeStatoFinanziamento(Entity $entity): void
    {
        $current = trim((string) ($entity->get('statoFinanziamento') ?? ''));

        if ($current === '' || !isset(self::FINANZIAMENTO_ALIASES[$current])) {
            return;
        }

        $entity->set('statoFinanziamento', self::FINANZIAMENTO_ALIASES[$current]);
    }
}
