<?php

// ========================================
// VERSIONE: 1.0.5
// DATA: 2026-06-08
// ----------------------------------------
// FIX 1.0.5:
// ✔ Modifica appuntamento con prospect: non bloccare se esiste ghost nello slot
// ✔ Rimuove ghost PRIMA del controllo duplicati (beforeSave), non solo dopo
//
// FIX 1.0.4:
// ✔ Blocco simmetrico ghost ↔ appuntamento con prospect (stesso slot + assegnatario)
// ✔ Dopo save con prospect: rimuove ghost "(APPUNTAMENTO SENZA PROSPECT)" nello stesso slot
// ========================================

namespace Espo\Custom\Hooks\Appuntamento;

use Espo\Core\Exceptions\Error;
use Espo\Core\Hook\Hook\AfterSave;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * @implements BeforeSave<Entity>
 * @implements AfterSave<Entity>
 */
class PreventDuplicate implements BeforeSave, AfterSave
{
    private const ENTITY_TYPE = 'Appuntamento';

    private const GHOST_NAME_MARKER = '(APPUNTAMENTO SENZA PROSPECT)';

    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('isImport') || $options->get('skipHooks')) {
            return;
        }

        $start = $entity->get('dateStart');
        $end = $entity->get('dateEnd');

        if (!$start || !$end) {
            return;
        }

        if ($this->buildIdentityKey($entity) !== null) {
            $this->purgeGhostsInSlot($entity);
        }

        $identityKey = $this->buildIdentityKey($entity);
        $googleEventId = $this->resolveGoogleCalendarEventId($entity);

        $query = $this->entityManager
            ->getRDBRepository(self::ENTITY_TYPE)
            ->where([
                'dateStart' => $start,
                'dateEnd' => $end,
            ]);

        if (!$entity->isNew()) {
            $query->where(['id!=' => $entity->getId()]);
        }

        foreach ($query->find() as $existing) {
            if ($this->isDuplicate($entity, $existing, $identityKey, $googleEventId)) {
                throw new Error('Appuntamento duplicato bloccato');
            }
        }
    }

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('skipHooks')) {
            return;
        }

        $this->purgeGhostsInSlot($entity);

        $operation = $entity->isNew() ? 'CREATE' : 'UPDATE';
        $log = date('Y-m-d H:i:s') .
            ' | Appuntamento | ' . $operation .
            ' | ID: ' . $entity->getId() .
            ' | START: ' . $entity->get('dateStart') .
            ' | END: ' . $entity->get('dateEnd') . PHP_EOL;

        $logPath = 'data/logs/custom.log';

        if (is_writable(dirname($logPath)) || is_file($logPath)) {
            file_put_contents($logPath, $log, FILE_APPEND);
        }
    }

    /**
     * Chiave cliente per il controllo duplicati (prospect > parent > indirizzo).
     */
    private function buildIdentityKey(Entity $entity): ?string
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

    private function isDuplicate(
        Entity $entity,
        Entity $existing,
        ?string $identityKey,
        ?string $googleEventId
    ): bool {
        if ($identityKey !== null && $this->isGhostAppointment($existing)) {
            return false;
        }

        if ($googleEventId !== null) {
            $existingGoogleEventId = $this->resolveGoogleCalendarEventId($existing);

            if ($existingGoogleEventId !== null && $existingGoogleEventId === $googleEventId) {
                return true;
            }
        }

        $existingKey = $this->buildIdentityKey($existing);

        if ($identityKey !== null && $identityKey === $existingKey) {
            return true;
        }

        if (!$this->sameAssignedUser($entity, $existing)) {
            return false;
        }

        if ($identityKey === null && $this->isGhostAppointment($entity) && $existingKey !== null) {
            return true;
        }

        if ($identityKey === null && $existingKey === null
            && $this->isGhostAppointment($entity) && $this->isGhostAppointment($existing)) {
            return true;
        }

        return false;
    }

    private function purgeGhostsInSlot(Entity $entity): void
    {
        if ($this->buildIdentityKey($entity) === null) {
            return;
        }

        $start = $entity->get('dateStart');
        $end = $entity->get('dateEnd');

        if (!$start || !$end) {
            return;
        }

        $query = [
            'dateStart' => $start,
            'dateEnd' => $end,
        ];

        if (!$entity->isNew() && $entity->getId()) {
            $query['id!='] = $entity->getId();
        }

        $assignedUserId = $entity->get('assignedUserId');

        if ($assignedUserId) {
            $query['assignedUserId'] = $assignedUserId;
        }

        foreach ($this->entityManager->getRDBRepository(self::ENTITY_TYPE)->where($query)->find() as $ghost) {
            if (!$this->isGhostAppointment($ghost)) {
                continue;
            }

            $this->entityManager->removeEntity($ghost, [
                'silent' => true,
                'skipHooks' => true,
            ]);
        }
    }

    private function isGhostAppointment(Entity $entity): bool
    {
        if ($this->buildIdentityKey($entity) !== null) {
            return false;
        }

        $name = (string) ($entity->get('name') ?? '');

        return str_contains($name, self::GHOST_NAME_MARKER);
    }

    private function sameAssignedUser(Entity $entity, Entity $existing): bool
    {
        $userId = $entity->get('assignedUserId');
        $existingUserId = $existing->get('assignedUserId');

        if (!$userId || !$existingUserId) {
            return true;
        }

        return $userId === $existingUserId;
    }

    private function resolveGoogleCalendarEventId(Entity $entity): ?string
    {
        if (!$entity->getId()) {
            return null;
        }

        $relation = $this->entityManager
            ->getRDBRepository('GoogleCalendarEvent')
            ->where([
                'entityType' => self::ENTITY_TYPE,
                'entityId' => $entity->getId(),
                ['googleCalendarEventId!=' => ''],
                ['googleCalendarEventId!=' => 'FAIL'],
                ['googleCalendarEventId!=' => null],
            ])
            ->findOne();

        if (!$relation) {
            return null;
        }

        $eventId = $relation->get('googleCalendarEventId');

        return is_string($eventId) && $eventId !== '' ? $eventId : null;
    }
}
