<?php

namespace Espo\Custom\Hooks\Call;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Core\Utils\Log;
use Espo\Custom\Services\AppuntamentoPendingCallCreator;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Rinvio richiamo Call:
 * - Pianificato + daRichiamare → sposta dateStart (stessa Call)
 * - Svolto/Non svolto + daRichiamare → nuova Call pianificata
 */
class RinvioRichiamo implements BeforeSave, AfterSave
{
    public static int $order = 7;

    public function __construct(
        private EntityManager $entityManager,
        private Log $log,
        private AppuntamentoPendingCallCreator $callCreator,
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('skipHooks') || $options->get('isImport')) {
            return;
        }

        if ($entity->getEntityType() !== 'Call') {
            return;
        }

        if (!$entity->get('daRichiamare')) {
            return;
        }

        try {
            $this->callCreator->applyRinvioDefaultsToEntity($entity);

            if ((string) $entity->get('status') === 'Planned') {
                $this->callCreator->applyRinvioToEntity($entity);
            }
        } catch (\Throwable $e) {
            $this->log->error(
                'Rinvio richiamo Call {id} fallito: {message}',
                [
                    'id' => $entity->getId(),
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]
            );
        }
    }

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('skipHooks') || $options->get('isImport')) {
            return;
        }

        if ($entity->getEntityType() !== 'Call') {
            return;
        }

        $status = (string) $entity->get('status');
        $callId = $entity->getId();

        if (!$callId) {
            return;
        }

        try {
            if (
                in_array($status, ['Held', 'Not Held'], true)
                && $entity->get('daRichiamare')
                && $entity->get('dataRichiamo')
                && (
                    $entity->isNew()
                    || $entity->isAttributeChanged('daRichiamare')
                    || $entity->isAttributeChanged('dataRichiamo')
                    || $entity->isAttributeChanged('richiamo')
                    || $entity->isAttributeChanged('status')
                )
            ) {
                $this->callCreator->createFollowUpFromCall($entity);
                $this->callCreator->clearRinvioFlagsOnCall($callId);
            }

            if (in_array($status, ['Held', 'Not Held'], true)) {
                $this->callCreator->clearPopupReminders($entity);
            }

            if ($status === 'Planned') {
                $fresh = $this->entityManager->getEntityById('Call', $callId);

                if ($fresh) {
                    $this->callCreator->syncPopupReminders($fresh);
                }
            }
        } catch (\Throwable $e) {
            $this->log->error(
                'Follow-up richiamo Call {id} fallito: {message}',
                [
                    'id' => $callId,
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]
            );
        }
    }
}
