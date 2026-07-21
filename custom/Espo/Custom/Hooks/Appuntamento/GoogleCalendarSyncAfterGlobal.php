<?php

namespace Espo\Custom\Hooks\Appuntamento;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Custom\Services\AppuntamentoGoogleSync;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Dopo GlobalLogic (order 9): rimuove da Google, poi consulente → admin su Not Held.
 *
 * @implements BeforeSave<Entity>
 */
class GoogleCalendarSyncAfterGlobal implements BeforeSave
{
    public static int $order = 10;

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

        if ($this->appuntamentoGoogleSync->shouldRemoveFromGoogleCalendar($entity)) {
            $this->appuntamentoGoogleSync->handleNotHeldStatus($entity);
        }

        $this->appuntamentoGoogleSync->syncAssignedUserIdFromAssignedUsers($entity);
        $this->appuntamentoGoogleSync->handleConsultantChange($entity);
    }
}
