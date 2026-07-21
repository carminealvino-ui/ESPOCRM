<?php

namespace Espo\Custom\Hooks\Appuntamento;

use Espo\Core\ApplicationUser;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Garantisce assegnatario su nuovo Appuntamento (calendario / quick create).
 *
 * Appuntamento usa assignedUsers (multi): il calendario Espo passa solo assignedUserId
 * e, con preferenza "non pre-compilare assegnatario", il client può lasciare entrambi vuoti.
 */
class AssignCreatorOnCreate implements BeforeSave
{
    public static int $order = 1;

    public function __construct(
        private EntityManager $entityManager,
        private ApplicationUser $applicationUser
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('skipHooks') || $options->get('isImport')) {
            return;
        }

        if (!$entity->isNew()) {
            return;
        }

        $assignedUsersIds = $this->normalizeIds($entity->get('assignedUsersIds'));

        if ($assignedUsersIds !== []) {
            $this->syncAssignedUserId($entity, $assignedUsersIds[0]);

            return;
        }

        $userId = trim((string) ($entity->get('assignedUserId') ?: ''));

        if ($userId === '') {
            $userId = trim((string) ($entity->get('createdById') ?: ''));
        }

        if ($userId === '') {
            $userId = $this->applicationUser->getId();
        }

        if ($userId === '') {
            return;
        }

        $entity->set('assignedUsersIds', [$userId]);
        $this->syncAssignedUserId($entity, $userId);
    }

    /**
     * @param mixed $ids
     * @return list<string>
     */
    private function normalizeIds(mixed $ids): array
    {
        if (!is_array($ids)) {
            return [];
        }

        $normalized = [];

        foreach ($ids as $id) {
            $id = trim((string) $id);

            if ($id !== '') {
                $normalized[] = $id;
            }
        }

        return $normalized;
    }

    private function syncAssignedUserId(Entity $entity, string $userId): void
    {
        if ((string) $entity->get('assignedUserId') === $userId) {
            return;
        }

        $entity->set('assignedUserId', $userId);

        $user = $this->entityManager->getEntityById('User', $userId);

        if ($user) {
            $entity->set('assignedUserName', $user->get('name'));
        }
    }
}
