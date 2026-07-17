<?php

namespace Espo\Custom\Hooks\Call;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Custom\Services\AppuntamentoPendingCallCreator;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Rimuove promemoria popup quando la Call non è più Pianificata.
 */
class ClearPopupRemindersOnComplete implements AfterSave
{
    public static int $order = 8;

    public function __construct(
        private AppuntamentoPendingCallCreator $callCreator,
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('skipHooks') || $options->get('isImport')) {
            return;
        }

        if ($entity->getEntityType() !== 'Call') {
            return;
        }

        $status = (string) $entity->get('status');

        if ($status === 'Planned') {
            return;
        }

        if (!in_array($status, ['Held', 'Not Held'], true)) {
            return;
        }

        if (
            !$entity->isNew()
            && !$entity->isAttributeChanged('status')
        ) {
            return;
        }

        $this->callCreator->clearPopupReminders($entity);
    }
}
