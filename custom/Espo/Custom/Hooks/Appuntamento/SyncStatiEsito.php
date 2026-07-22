<?php

namespace Espo\Custom\Hooks\Appuntamento;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Custom\Services\AppuntamentoStatiRules;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Allinea status / sottostato / esito prima di GlobalLogic.
 *
 * @implements BeforeSave<Entity>
 */
class SyncStatiEsito implements BeforeSave
{
    public static int $order = 7;

    public function __construct(
        private AppuntamentoStatiRules $rules
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($entity->getEntityType() !== 'Appuntamento') {
            return;
        }

        if ($options->get('silent') || $options->get('skipHooks')) {
            return;
        }

        $this->rules->apply($entity);
    }
}
