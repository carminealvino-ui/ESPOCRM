<?php

namespace Espo\Custom\Hooks\Appuntamento;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Core\Utils\Log;
use Espo\Custom\Services\AppuntamentoPendingCallCreator;
use Espo\Custom\Services\CallStandardTesto;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Appuntamento con richiamo flaggato → Call pianificata su dataRichiamo.
 */
class CreateCallFromRichiamo implements AfterSave
{
    public static int $order = 6;

    public function __construct(
        private EntityManager $entityManager,
        private Log $log,
        private CallStandardTesto $standardTesto,
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('skipHooks') || $options->get('isImport')) {
            return;
        }

        if ($options->get('skipAutoCreatePendingCall')) {
            return;
        }

        if ($entity->getEntityType() !== 'Appuntamento') {
            return;
        }

        if (!$entity->get('daRichiamare')) {
            return;
        }

        if (!$entity->get('dataRichiamo') || !$entity->get('richiamo')) {
            return;
        }

        if (
            !$entity->isNew()
            && !$entity->isAttributeChanged('daRichiamare')
            && !$entity->isAttributeChanged('dataRichiamo')
            && !$entity->isAttributeChanged('richiamo')
        ) {
            return;
        }

        try {
            $creator = new AppuntamentoPendingCallCreator(
                $this->entityManager,
                $this->log,
                $this->standardTesto
            );

            $creator->createRichiamoIfNeeded($entity);
        } catch (\Throwable $e) {
            $this->log->error(
                'Auto-create Call richiamo per Appuntamento {id} fallita: {message}',
                [
                    'id' => $entity->getId(),
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]
            );
        }
    }
}
