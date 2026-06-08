<?php

namespace Espo\Custom\Hooks\Appuntamento;

use Espo\Core\Hook\Hook\AfterRemove;
use Espo\Core\Hook\Hook\AfterSave;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Custom\Services\AppuntamentoGoogleSync;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\RemoveOptions;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Google Calendar: Not Held → rimuovi; Ingestibile → resta; cambio consulente → sposta.
 *
 * @implements BeforeSave<Entity>
 * @implements AfterSave<Entity>
 * @implements AfterRemove<Entity>
 */
class GoogleCalendarSync implements BeforeSave, AfterSave, AfterRemove
{
    public static int $order = 6;

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

        $this->appuntamentoGoogleSync->syncAssignedUserIdFromAssignedUsers($entity);
        $this->appuntamentoGoogleSync->handleConsultantChange($entity);
    }

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('skipHooks') || $options->get('isImport')) {
            return;
        }

        if ($entity->getEntityType() !== 'Appuntamento') {
            return;
        }

        $this->appuntamentoGoogleSync->handleNotHeldStatus($entity);
    }

    public function afterRemove(Entity $entity, RemoveOptions $options): void
    {
        if ($options->get('skipHooks')) {
            return;
        }

        if ($entity->getEntityType() !== 'Appuntamento') {
            return;
        }

        $this->appuntamentoGoogleSync->handleRemoved($entity);
    }
}
