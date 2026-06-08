<?php

namespace Espo\Custom\Hooks\Appuntamento;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Custom\Services\AppuntamentoGoogleSync;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Prima di GlobalLogic (order 9): rimuove da Google i Not Held mentre il consulente è ancora assegnato.
 *
 * @implements BeforeSave<Entity>
 */
class GoogleCalendarSyncBeforeGlobal implements BeforeSave
{
    public static int $order = 8;

    public function __construct(
        private AppuntamentoGoogleSync $appuntamentoGoogleSync
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('skipHooks') || $options->get('isImport')) {
            return;
        }

        if ($entity->getEntityType() !== 'Appuntamento') {
            return;
        }

        if ($entity->get('status') !== 'Not Held') {
            return;
        }

        $this->appuntamentoGoogleSync->handleNotHeldStatus($entity);
    }
}
