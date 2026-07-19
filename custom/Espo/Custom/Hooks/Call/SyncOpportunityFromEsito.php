<?php

namespace Espo\Custom\Hooks\Call;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Custom\Services\CallEsitoOpportunitySync;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Esito "Non interessato" sul riscontro richiamo:
 * - opportunità collegata → Closed Lost (Chiusa negativamente)
 * - lead collegato → Perso (Dead)
 * - appuntamento Pending collegato → Non Interessato
 */
class SyncOpportunityFromEsito implements AfterSave
{
    public static int $order = 11;

    public function __construct(
        private CallEsitoOpportunitySync $sync,
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('skipHooks') || $options->get('isImport')) {
            return;
        }

        $this->sync->syncFromCall($entity);
    }
}
