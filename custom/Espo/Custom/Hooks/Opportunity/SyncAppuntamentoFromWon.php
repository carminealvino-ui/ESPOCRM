<?php

namespace Espo\Custom\Hooks\Opportunity;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Custom\Services\OpportunityAppuntamentoOutcomeSync;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Opportunità vinta/installata → allinea Appuntamento collegato
 * (Held + Chiuso Positivamente + Venduto).
 */
class SyncAppuntamentoFromWon implements AfterSave
{
    public static int $order = 25;

    public function __construct(
        private OpportunityAppuntamentoOutcomeSync $sync
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('silent') || $options->get('skipHooks')) {
            return;
        }

        if ($entity->getEntityType() !== 'Opportunity' || !$entity->getId()) {
            return;
        }

        if (
            !$entity->isNew()
            && !$entity->isAttributeChanged('stage')
            && !$entity->isAttributeChanged('statoContratto')
            && !$entity->isAttributeChanged('appuntamentoId')
        ) {
            return;
        }

        if (!$this->sync->isWonOpportunity($entity)) {
            return;
        }

        $this->sync->syncFromOpportunity($entity, false);
    }
}
