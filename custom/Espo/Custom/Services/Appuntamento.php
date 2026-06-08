<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;

class Appuntamento extends \Espo\Core\Templates\Services\Event
{
    private const GHOST_NAME_MARKER = '(APPUNTAMENTO SENZA PROSPECT)';

    public function createEntity($data)
    {
        $duplicate = $this->findDuplicateForCreate($data);

        if ($duplicate) {
            return $duplicate;
        }

        return parent::createEntity($data);
    }

    private function findDuplicateForCreate(object $data): ?Entity
    {
        if (empty($data->dateStart) || empty($data->dateEnd)) {
            return null;
        }

        $where = [
            'dateStart' => $data->dateStart,
            'dateEnd' => $data->dateEnd,
        ];

        if (!empty($data->assignedUserId)) {
            $where['assignedUserId'] = $data->assignedUserId;
        }

        $candidates = $this->getEntityManager()
            ->getRDBRepository('Appuntamento')
            ->where($where)
            ->order('createdAt', 'DESC')
            ->find();

        $incomingKey = $this->buildIdentityKeyFromData($data);
        $incomingIsGhost = $this->isGhostName((string) ($data->name ?? '')) && $incomingKey === null;

        foreach ($candidates as $existing) {
            $existingKey = $this->buildIdentityKeyFromEntity($existing);

            if ($incomingKey !== null && $incomingKey === $existingKey) {
                return $existing;
            }

            if ($incomingIsGhost && $existingKey !== null) {
                return $existing;
            }

            if ($incomingIsGhost && $existingKey === null) {
                $createdAt = strtotime((string) $existing->get('createdAt'));
                $now = time();

                if ($createdAt !== false && ($now - $createdAt) < 3) {
                    return $existing;
                }
            }
        }

        return null;
    }

    private function buildIdentityKeyFromData(object $data): ?string
    {
        if (!empty($data->prospectId)) {
            return 'prospect:' . $data->prospectId;
        }

        if (!empty($data->parentType) && !empty($data->parentId)) {
            return 'parent:' . $data->parentType . ':' . $data->parentId;
        }

        $indirizzo = mb_strtolower(trim((string) ($data->indirizzo ?? '')));

        if ($indirizzo !== '') {
            return 'indirizzo:' . $indirizzo;
        }

        return null;
    }

    private function buildIdentityKeyFromEntity(Entity $entity): ?string
    {
        $prospectId = $entity->get('prospectId');

        if ($prospectId) {
            return 'prospect:' . $prospectId;
        }

        $parentType = $entity->get('parentType');
        $parentId = $entity->get('parentId');

        if ($parentType && $parentId) {
            return 'parent:' . $parentType . ':' . $parentId;
        }

        $indirizzo = mb_strtolower(trim((string) $entity->get('indirizzo')));

        if ($indirizzo !== '') {
            return 'indirizzo:' . $indirizzo;
        }

        return null;
    }

    private function isGhostName(string $name): bool
    {
        return str_contains($name, self::GHOST_NAME_MARKER);
    }
}
