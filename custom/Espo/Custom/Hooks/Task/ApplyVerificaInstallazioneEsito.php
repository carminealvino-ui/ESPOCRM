<?php

namespace Espo\Custom\Hooks\Task;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Custom\Services\QuoteInstallazioneVerificaTaskSync;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Esito To-Do verifica installazione → aggiorna Contratto (Installato/Chiuso o rinviato).
 *
 * @implements AfterSave<Entity>
 */
class ApplyVerificaInstallazioneEsito implements AfterSave
{
    public static int $order = 15;

    public function __construct(
        private QuoteInstallazioneVerificaTaskSync $sync
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($entity->getEntityType() !== 'Task') {
            return;
        }

        if ($options->get('skipHooks')) {
            return;
        }

        if (!(bool) $entity->get('verificaInstallazioneContratto')) {
            return;
        }

        if (!$entity->isAttributeChanged('esitoVerificaInstallazione')) {
            return;
        }

        $this->sync->applyEsitoFromTask($entity);
    }
}
