<?php

namespace Espo\Custom\Hooks\Appuntamento;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Core\Utils\Log;
use Espo\Custom\Services\AppuntamentoPendingCallCreator;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Appuntamento Held + sottostato Pending → Call pianificata +2 giorni alle 9:30
 * (weekend slittato al lunedì), con promemoria popup.
 */
class AutoCreatePendingCall implements AfterSave
{
    public static int $order = 7;

    public function __construct(
        private EntityManager $entityManager,
        private Log $log
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

        try {
            $creator = new AppuntamentoPendingCallCreator($this->entityManager, $this->log);

            $creator->createIfNeeded($entity);
        } catch (\Throwable $e) {
            $this->log->warning(
                'Auto-create Call Pending per Appuntamento {id} fallita: {message}',
                [
                    'id' => $entity->getId(),
                    'message' => $e->getMessage(),
                ]
            );
        }
    }
}
