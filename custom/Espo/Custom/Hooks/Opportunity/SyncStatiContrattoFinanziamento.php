<?php

namespace Espo\Custom\Hooks\Opportunity;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Custom\Services\ContrattoStatiRules;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Stesse regole del Contratto su Opportunità.
 *
 * @implements BeforeSave<Entity>
 */
class SyncStatiContrattoFinanziamento implements BeforeSave
{
    public static int $order = 12;

    public function __construct(
        private ContrattoStatiRules $rules
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($entity->getEntityType() !== 'Opportunity') {
            return;
        }

        if ($options->get('silent') || $options->get('skipHooks')) {
            return;
        }

        $this->rules->apply($entity);
    }
}
