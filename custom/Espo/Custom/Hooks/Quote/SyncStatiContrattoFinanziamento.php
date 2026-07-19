<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Custom\Services\ContrattoStatiRules;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Recesso → Finanziamento Annullato; Chiuso solo se Approvato.
 *
 * @implements BeforeSave<Entity>
 */
class SyncStatiContrattoFinanziamento implements BeforeSave
{
    /** Dopo SyncFinanziamentoFromOpportunity (4) e NormalizeDefaults (5). */
    public static int $order = 12;

    public function __construct(
        private ContrattoStatiRules $rules
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($entity->getEntityType() !== 'Quote') {
            return;
        }

        if ($options->get('silent') || $options->get('skipHooks')) {
            return;
        }

        $this->rules->apply($entity);
    }
}
