<?php

// ========================================
// VERSIONE: 1.0.3
// DATA: 2026-06-06
// AUTORE: CARMINE ALVINO + CHATGPT
// ----------------------------------------
// FIX 1.0.3:
// ✔ Hook registrato in metadata/hooks/Appuntamento.json
// ✔ Blocco ghost Google Calendar (APPUNTAMENTO SENZA PROSPECT)
//   stesso slot + stesso assegnatario
// ✔ Duplicato Google Calendar stesso googleCalendarEventId
//
// FIX 1.0.2:
// ✔ Duplicato = stesso slot (dateStart + dateEnd) E stesso cliente
//   (prospect, oppure parent, oppure indirizzo)
// ✔ Prospect/indirizzo diversi → stesso orario consentito
//
// ROLLBACK:
// backup_dev/Appuntamento/hooks/preventduplicate-1.0.1-data-only-stabile.php
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

        // Ghost Google Calendar: esiste gia' un appuntamento con cliente nello stesso slot.
        if ($identityKey === null && $existingKey !== null) {
            return true;
        }

        // Entrambi senza cliente (es. doppio APPUNTAMENTO SENZA PROSPECT).
        if ($identityKey === null && $existingKey === null) {
            return true;
        }

        return false;
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

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
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
}
