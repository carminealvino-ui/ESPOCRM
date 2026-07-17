<?php

namespace Espo\Custom\Hooks\Call;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Custom\Services\AppuntamentoPendingCallCreator;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

class SyncOwnerFromAppuntamento implements BeforeSave
{
    public static int $order = 4;

    public function __construct(
        private EntityManager $entityManager,
        private AppuntamentoPendingCallCreator $callCreator,
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('skipHooks') || $options->get('isImport')) {
            return;
        }

        $appuntamentoId = $this->callCreator->extractAppuntamentoIdFromNota((string) $entity->get('nota'));

        if (!$appuntamentoId) {
            return;
        }

        $appuntamento = $this->entityManager->getEntityById('Appuntamento', $appuntamentoId);

        if (!$appuntamento) {
            return;
        }

        $this->callCreator->syncCallNameFromAppuntamento($entity, $appuntamento);

        $ownerUserId = $this->callCreator->resolveOwnerUserId($appuntamento);

        if (!$ownerUserId) {
            return;
        }

        if ((string) $entity->get('assignedUserId') === $ownerUserId) {
            return;
        }

        $ownerUserName = $this->entityManager->getEntityById('User', $ownerUserId)?->get('name');

        $entity->set([
            'assignedUserId' => $ownerUserId,
            'assignedUserName' => $ownerUserName,
            'usersIds' => [$ownerUserId],
            'usersNames' => $ownerUserName ? [$ownerUserId => $ownerUserName] : (object) [],
        ]);
    }
}
