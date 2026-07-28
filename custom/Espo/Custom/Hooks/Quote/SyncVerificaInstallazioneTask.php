<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Custom\Services\QuoteInstallazioneVerificaTaskSync;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Crea/aggiorna To-Do promemoria quando cambia dataInstallazione.
 *
 * @implements AfterSave<Entity>
 */
class SyncVerificaInstallazioneTask implements AfterSave
{
    public static int $order = 25;

    public function __construct(
        private QuoteInstallazioneVerificaTaskSync $sync
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($entity->getEntityType() !== 'Quote') {
            return;
        }

        if ($options->get('silent') && $options->get('skipHooks')) {
            return;
        }

        $installChanged = $entity->isAttributeChanged('dataInstallazione');
        $needsTask = $this->normalizeHasInstallDate($entity)
            && !$entity->get('verificaInstallazioneTaskId');

        if (!$installChanged && !$needsTask) {
            return;
        }

        $this->sync->syncFromQuote($entity);
    }

    private function normalizeHasInstallDate(Entity $entity): bool
    {
        $value = $entity->get('dataInstallazione');

        if ($value instanceof \DateTimeInterface) {
            return true;
        }

        return is_string($value) && trim($value) !== '';
    }
}
