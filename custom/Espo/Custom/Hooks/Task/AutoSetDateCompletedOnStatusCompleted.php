<?php

namespace Espo\Custom\Hooks\Task;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Custom\Tools\DateTime\BusinessDateTime;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Evita errore popup To-Do: se status=Completed e dateCompleted è vuota,
 * la valorizza automaticamente.
 *
 * @implements BeforeSave<Entity>
 */
class AutoSetDateCompletedOnStatusCompleted implements BeforeSave
{
    public static int $order = 5;

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($entity->getEntityType() !== 'Task') {
            return;
        }

        $status = (string) ($entity->get('status') ?? '');

        if ($status !== 'Completed') {
            return;
        }

        $dateCompleted = $entity->get('dateCompleted');

        if ($dateCompleted !== null && (string) $dateCompleted !== '') {
            return;
        }

        $entity->set('dateCompleted', gmdate(BusinessDateTime::STORAGE_FORMAT));
    }
}
