<?php

namespace Espo\Custom\Services;

use Espo\Core\ExternalAccount\ClientManager;
use Espo\Modules\Google\Repositories\GoogleCalendar as GoogleCalendarRepository;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Sync Appuntamento ↔ Google Calendar (rimozione su Not Held / delete / cambio consulente).
 */
class AppuntamentoGoogleSync
{
    private const ENTITY_TYPE = 'Appuntamento';

    public function __construct(
        private EntityManager $entityManager,
        private ClientManager $clientManager
    ) {}

    public function syncAssignedUserIdFromAssignedUsers(Entity $entity): void
    {
        if ($entity->getEntityType() !== self::ENTITY_TYPE) {
            return;
        }

        $ids = $entity->get('assignedUsersIds');

        if (!is_array($ids) || $ids === []) {
            return;
        }

        $primaryUserId = (string) $ids[0];

        if ($primaryUserId === '') {
            return;
        }

        if ($entity->get('assignedUserId') !== $primaryUserId) {
            $entity->set('assignedUserId', $primaryUserId);
        }
    }

    public function handleConsultantChange(Entity $entity): void
    {
        if ($entity->isNew() || $entity->getEntityType() !== self::ENTITY_TYPE) {
            return;
        }

        $oldUserId = $this->resolvePrimaryUserId(
            $entity->getFetched('assignedUsersIds'),
            $entity->getFetched('assignedUserId')
        );
        $newUserId = $this->resolvePrimaryUserId(
            $entity->get('assignedUsersIds'),
            $entity->get('assignedUserId')
        );

        if ($oldUserId === null || $newUserId === null || $oldUserId === $newUserId) {
            return;
        }

        if (!$this->hasGoogleLink($entity->getId())) {
            return;
        }

        $this->unlinkFromGoogleForUser($entity, $oldUserId);
    }

    public function handleNotHeldStatus(Entity $entity): void
    {
        if ($entity->getEntityType() !== self::ENTITY_TYPE) {
            return;
        }

        if ($entity->get('status') !== 'Not Held') {
            return;
        }

        if (!$entity->isNew() && !$entity->isAttributeChanged('status')) {
            return;
        }

        $userId = $this->resolvePrimaryUserId(
            $entity->getFetched('assignedUsersIds'),
            $entity->getFetched('assignedUserId')
        );

        $this->unlinkFromGoogleForUser($entity, $userId);
    }

    public function handleRemoved(Entity $entity): void
    {
        if ($entity->getEntityType() !== self::ENTITY_TYPE) {
            return;
        }

        $userId = $this->resolvePrimaryUserId(
            $entity->get('assignedUsersIds'),
            $entity->get('assignedUserId')
        );

        $this->unlinkFromGoogleForUser($entity, $userId);
    }

    private function unlinkFromGoogleForUser(Entity $entity, ?string $userId): void
    {
        $entityId = $entity->getId();

        if (!$entityId) {
            return;
        }

        $googleData = $this->getGoogleRepository()->getEventEntityGoogleData(self::ENTITY_TYPE, $entityId);

        if ($googleData === false || empty($googleData['googleCalendarEventId'])) {
            return;
        }

        $eventId = (string) $googleData['googleCalendarEventId'];

        if ($eventId === '' || $eventId === 'FAIL') {
            $this->getGoogleRepository()->resetEventRelation(self::ENTITY_TYPE, $entityId);

            return;
        }

        if ($userId !== null && $this->deleteGoogleEventForUser($userId, $googleData)) {
            $this->getGoogleRepository()->resetEventRelation(self::ENTITY_TYPE, $entityId);

            return;
        }

        $this->queueDeletedDummyForSyncJob($entity, $googleData, $userId);
        $this->getGoogleRepository()->resetEventRelation(self::ENTITY_TYPE, $entityId);
    }

    /**
     * @param array<string, mixed> $googleData
     */
    private function deleteGoogleEventForUser(string $userId, array $googleData): bool
    {
        if (!$this->isCalendarPushEnabledForUser($userId)) {
            return false;
        }

        $googleCalendar = $this->entityManager->getEntityById(
            'GoogleCalendar',
            $googleData['googleCalendarId']
        );

        if (!$googleCalendar) {
            return false;
        }

        $calendarId = $googleCalendar->get('calendarId');

        if (!is_string($calendarId) || $calendarId === '') {
            return false;
        }

        try {
            $client = $this->clientManager->create('Google', $userId);

            return (bool) $client->getCalendarClient()->deleteEvent(
                $calendarId,
                $googleData['googleCalendarEventId']
            );
        } catch (\Throwable $e) {
            $GLOBALS['log']->error(
                'AppuntamentoGoogleSync: delete failed for user ' . $userId . ': ' . $e->getMessage()
            );

            return false;
        }
    }

    /**
     * Fallback: record segnato deleted collegato a Google per il job Espo→GC.
     *
     * @param array<string, mixed> $googleData
     */
    private function queueDeletedDummyForSyncJob(Entity $entity, array $googleData, ?string $userId): void
    {
        if ($userId === null) {
            return;
        }

        $dummy = $this->entityManager->getNewEntity(self::ENTITY_TYPE);

        $copyFields = ['name', 'dateStart', 'dateEnd', 'location'];

        foreach ($copyFields as $field) {
            if ($dummy->hasAttribute($field)) {
                $dummy->set($field, $entity->getFetched($field) ?? $entity->get($field));
            }
        }

        $dummy->set('assignedUserId', $userId);
        $dummy->set('deleted', true);

        $this->entityManager->saveEntity($dummy, ['skipHooks' => true]);

        $this->getGoogleRepository()->storeEventRelation(
            self::ENTITY_TYPE,
            $dummy->getId(),
            $googleData['googleCalendarId'],
            $googleData['googleCalendarEventId']
        );

        $this->entityManager->removeEntity($dummy, ['skipHooks' => true]);
    }

    private function hasGoogleLink(string $entityId): bool
    {
        $googleData = $this->getGoogleRepository()->getEventEntityGoogleData(self::ENTITY_TYPE, $entityId);

        return is_array($googleData) && !empty($googleData['googleCalendarEventId']);
    }

    private function isCalendarPushEnabledForUser(string $userId): bool
    {
        $externalAccount = $this->entityManager->getEntityById('ExternalAccount', 'Google__' . $userId);

        if (!$externalAccount || !$externalAccount->get('enabled')) {
            return false;
        }

        if (!$externalAccount->get('calendarEnabled') && !$externalAccount->get('googleCalendarEnabled')) {
            return false;
        }

        $direction = $externalAccount->get('calendarDirection');

        if ($direction === 'GCToEspo') {
            return false;
        }

        $entityTypes = $externalAccount->get('calendarEntityTypes');

        if (is_array($entityTypes) && $entityTypes !== [] && !in_array('Appuntamento', $entityTypes, true)) {
            return false;
        }

        return true;
    }

    /**
     * @param mixed $assignedUsersIds
     */
    private function resolvePrimaryUserId(mixed $assignedUsersIds, mixed $assignedUserId): ?string
    {
        if (is_array($assignedUsersIds) && $assignedUsersIds !== []) {
            $id = (string) $assignedUsersIds[0];

            return $id !== '' ? $id : null;
        }

        if (is_string($assignedUserId) && $assignedUserId !== '') {
            return $assignedUserId;
        }

        return null;
    }

    private function getGoogleRepository(): GoogleCalendarRepository
    {
        /** @var GoogleCalendarRepository $repository */
        $repository = $this->entityManager->getRepository('GoogleCalendar');

        return $repository;
    }
}
