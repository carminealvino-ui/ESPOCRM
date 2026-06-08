<?php

namespace Espo\Custom\Services;

use Espo\Core\ExternalAccount\ClientManager;
use Espo\Core\InjectableFactory;
use Espo\Modules\Google\Core\Google\Actions\Event as GoogleEventAction;
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
        private ClientManager $clientManager,
        private InjectableFactory $injectableFactory
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

        if (!$this->hasGoogleLink($entity->getId())) {
            return;
        }

        $fallbackUserId = $this->resolvePrimaryUserId(
            $entity->getFetched('assignedUsersIds'),
            $entity->getFetched('assignedUserId')
        );

        $this->unlinkFromGoogleForUser($entity, $fallbackUserId);
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

    /**
     * Dopo il save: invia subito su Google gli appuntamenti pianificati senza link.
     * Il job Espo (ogni 3 min, max 20) spesso non basta e richiede assignedUserId corretto.
     */
    public function pushToGoogleIfNeeded(Entity $entity): bool
    {
        if ($entity->getEntityType() !== self::ENTITY_TYPE || !$entity->getId()) {
            return false;
        }

        if (!$this->shouldStayOnGoogleCalendar($entity)) {
            return false;
        }

        if ($this->hasGoogleLink($entity->getId())) {
            return false;
        }

        $this->loadAssignedUsersIds($entity);
        $userId = $this->resolvePrimaryUserId(
            $entity->get('assignedUsersIds'),
            $entity->get('assignedUserId')
        );

        if ($userId === null) {
            return false;
        }

        $this->persistAssignedUserIdIfNeeded($entity, $userId);

        return $this->pushEntityToGoogle($entity, $userId);
    }

    /**
     * Bonifica: corregge assignedUserId da assignedUsers (richiesto dal modulo Google).
     */
    public function bonificaFixAssignedUserId(Entity $entity): bool
    {
        $this->loadAssignedUsersIds($entity);
        $userId = $this->resolvePrimaryUserId(
            $entity->get('assignedUsersIds'),
            $entity->get('assignedUserId')
        );

        if ($userId === null) {
            return false;
        }

        return $this->persistAssignedUserIdIfNeeded($entity, $userId);
    }

    /**
     * @return 'pushed'|'skipped'|'failed'
     */
    public function bonificaPushMissing(Entity $entity, string $calendarUserId): string
    {
        if (!$this->shouldStayOnGoogleCalendar($entity)) {
            return 'skipped';
        }

        $this->bonificaFixAssignedUserId($entity);

        if ($this->hasGoogleLink($entity->getId())) {
            return 'skipped';
        }

        $userId = $this->resolvePrimaryUserId(
            $entity->get('assignedUsersIds'),
            $entity->get('assignedUserId')
        );

        if ($userId !== $calendarUserId) {
            return 'skipped';
        }

        return $this->pushEntityToGoogle($entity, $calendarUserId) ? 'pushed' : 'failed';
    }

    public function needsAssignedUserIdFix(Entity $entity): bool
    {
        $this->loadAssignedUsersIds($entity);
        $expected = $this->resolvePrimaryUserId(
            $entity->get('assignedUsersIds'),
            null
        );

        if ($expected === null) {
            return false;
        }

        return (string) $entity->get('assignedUserId') !== $expected;
    }

    /**
     * Bonifica: rimuove link Google per appuntamento che non deve restare in calendario.
     *
     * @return 'removed'|'skipped'|'failed'
     */
    public function bonificaRemoveIfStale(Entity $entity, string $calendarUserId): string
    {
        if (!$this->shouldStayOnGoogleCalendar($entity)) {
            return $this->bonificaForceRemoveGoogleLink($entity, $calendarUserId);
        }

        return 'skipped';
    }

    /**
     * @return 'removed'|'failed'|'no_link'
     */
    public function bonificaForceRemoveGoogleLink(Entity $entity, string $calendarUserId): string
    {
        $entityId = $entity->getId();

        if (!$entityId) {
            return 'failed';
        }

        $googleData = $this->getGoogleRepository()->getEventEntityGoogleData(self::ENTITY_TYPE, $entityId);

        if (is_array($googleData) && !empty($googleData['googleCalendarEventId'])) {
            if ($this->deleteGoogleEventWithFallback($googleData, $calendarUserId)) {
                $this->getGoogleRepository()->resetEventRelation(self::ENTITY_TYPE, $entityId);

                return 'removed';
            }

            return 'failed';
        }

        if ($this->bonificaDeleteOrphanGoogleEvent($entity, $calendarUserId)) {
            return 'removed';
        }

        return 'no_link';
    }

    /**
     * Cerca su Google Calendar un evento orfano (senza link Espo) per Not Held / annullati.
     */
    public function bonificaDeleteOrphanGoogleEvent(Entity $entity, string $calendarUserId): bool
    {
        if (!$this->isGoogleCalendarApiAvailableForUser($calendarUserId)) {
            return false;
        }

        $calendarId = $this->resolveMainGoogleCalendarIdForUser($calendarUserId);

        if ($calendarId === null) {
            return false;
        }

        $needle = $this->buildGoogleSearchNeedle($entity);

        if ($needle === null) {
            return false;
        }

        $dateStart = $entity->get('dateStart');

        if (!is_string($dateStart) || $dateStart === '') {
            return false;
        }

        try {
            $start = new \DateTime($dateStart);
        } catch (\Throwable) {
            return false;
        }

        $end = clone $start;
        $end->modify('+1 day');

        try {
            $client = $this->clientManager->create('Google', $calendarUserId);
            $response = $client->getCalendarClient()->getEventList($calendarId, [
                'timeMin' => $start->format('Y-m-d\T00:00:00\Z'),
                'timeMax' => $end->format('Y-m-d\T23:59:59\Z'),
                'singleEvents' => 'true',
                'maxResults' => 50,
                'q' => $needle,
            ]);
        } catch (\Throwable $e) {
            $GLOBALS['log']->error(
                'AppuntamentoGoogleSync: orphan search failed for user ' . $calendarUserId . ': ' . $e->getMessage()
            );

            return false;
        }

        if (!is_array($response) || empty($response['items']) || !is_array($response['items'])) {
            return false;
        }

        foreach ($response['items'] as $item) {
            if (!is_array($item) || empty($item['id'])) {
                continue;
            }

            $summary = (string) ($item['summary'] ?? '');

            if (!$this->googleSummaryMatchesAppointment($summary, $entity, $needle)) {
                continue;
            }

            try {
                $deleted = (bool) $client->getCalendarClient()->deleteEvent($calendarId, (string) $item['id']);

                if ($deleted) {
                    return true;
                }
            } catch (\Throwable $e) {
                $GLOBALS['log']->error(
                    'AppuntamentoGoogleSync: orphan delete failed ' . $item['id'] . ': ' . $e->getMessage()
                );
            }
        }

        return false;
    }

    public function shouldStayOnGoogleCalendar(Entity $entity): bool
    {
        if ($entity->getEntityType() !== self::ENTITY_TYPE) {
            return false;
        }

        if ($entity->get('deleted')) {
            return false;
        }

        $status = (string) ($entity->get('status') ?? '');

        if ($status === 'Not Held') {
            return false;
        }

        if ($this->isGhostAppointment($entity)) {
            return false;
        }

        return in_array($status, ['Planned', 'Held', 'Ingestibile'], true);
    }

    public function isGhostAppointment(Entity $entity): bool
    {
        $name = (string) ($entity->get('name') ?? '');

        if (!str_contains($name, '(APPUNTAMENTO SENZA PROSPECT)')) {
            return false;
        }

        return !$entity->get('prospectId')
            && !($entity->get('parentType') && $entity->get('parentId'));
    }

    /**
     * Ingestibile assegnato ad admin (errore GlobalLogic storico) → consulente calendario.
     */
    public function needsIngestibileConsultantFix(Entity $entity, string $consultantUserId): bool
    {
        if ($entity->get('status') !== 'Ingestibile' || $entity->get('deleted')) {
            return false;
        }

        $this->loadAssignedUsersIds($entity);

        if ($this->hasConsultantAssigned($entity, $consultantUserId)) {
            return false;
        }

        return $this->isAssignedToAdmin($entity);
    }

    public function isAssignedToAdmin(Entity $entity): bool
    {
        $this->loadAssignedUsersIds($entity);
        $adminIds = $this->resolveAdminUserIds();

        foreach ($entity->get('assignedUsersIds') ?: [] as $userId) {
            if (in_array((string) $userId, $adminIds, true)) {
                return true;
            }
        }

        $assignedUserId = $entity->get('assignedUserId');

        if ($assignedUserId && in_array((string) $assignedUserId, $adminIds, true)) {
            return true;
        }

        return false;
    }

    public function describeAssignee(Entity $entity): string
    {
        $this->loadAssignedUsersIds($entity);

        $names = $entity->get('assignedUsersNames');

        if (is_array($names) && $names !== []) {
            return implode(', ', $names);
        }

        if (is_string($names) && $names !== '') {
            return $names;
        }

        $name = $entity->get('assignedUserName');

        if (is_string($name) && $name !== '') {
            return $name;
        }

        $primaryId = $this->resolvePrimaryUserId(
            $entity->get('assignedUsersIds'),
            $entity->get('assignedUserId')
        );

        return $primaryId ?? '(nessuno)';
    }

    private function hasConsultantAssigned(Entity $entity, string $consultantUserId): bool
    {
        foreach ($entity->get('assignedUsersIds') ?: [] as $userId) {
            if ((string) $userId === $consultantUserId) {
                return true;
            }
        }

        return (string) $entity->get('assignedUserId') === $consultantUserId;
    }

    /**
     * Corregge Ingestibile assegnati per errore ad admin invece che al consulente.
     */
    public function bonificaFixIngestibileConsultant(Entity $entity, string $consultantUserId): bool
    {
        if (!$this->needsIngestibileConsultantFix($entity, $consultantUserId)) {
            return false;
        }

        $entity->set('assignedUsersIds', [$consultantUserId]);
        $entity->set('assignedUserId', $consultantUserId);
        $this->entityManager->saveEntity($entity, ['skipHooks' => true]);

        return true;
    }

    /**
     * @return string[]
     */
    public function resolveAdminUserIds(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $ids = ['1'];

        $users = $this->entityManager
            ->getRDBRepository('User')
            ->where([
                'isActive' => true,
                'OR' => [
                    ['type' => 'admin'],
                    ['userName' => 'admin'],
                    ['userName' => 'Admin'],
                ],
            ])
            ->find();

        foreach ($users as $user) {
            $ids[] = (string) $user->getId();
        }

        $cache = array_values(array_unique($ids));

        return $cache;
    }

    private function loadAssignedUsersIds(Entity $entity): void
    {
        if (!is_array($entity->get('assignedUsersIds'))) {
            $entity->loadLinkMultipleField('assignedUsers');
        }
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

        if ($this->deleteGoogleEventWithFallback($googleData, $userId)) {
            $this->getGoogleRepository()->resetEventRelation(self::ENTITY_TYPE, $entityId);

            return;
        }

        $syncUserId = $this->resolveCalendarOwnerUserId($googleData) ?? $userId;
        $this->queueDeletedDummyForSyncJob($entity, $googleData, $syncUserId);

        $GLOBALS['log']->warning(
            'AppuntamentoGoogleSync: Google delete failed, link kept for retry. Appuntamento=' . $entityId
        );
    }

    /**
     * @param array<string, mixed> $googleData
     */
    private function resolveCalendarOwnerUserId(array $googleData): ?string
    {
        $googleCalendarEntityId = $googleData['googleCalendarId'] ?? null;

        if (!is_string($googleCalendarEntityId) || $googleCalendarEntityId === '') {
            return null;
        }

        $gcUser = $this->entityManager
            ->getRDBRepository('GoogleCalendarUser')
            ->where([
                'googleCalendarId' => $googleCalendarEntityId,
                'active' => true,
                'type' => 'main',
            ])
            ->findOne();

        if ($gcUser) {
            $userId = $gcUser->get('userId');

            return is_string($userId) && $userId !== '' ? $userId : null;
        }

        $gcUser = $this->entityManager
            ->getRDBRepository('GoogleCalendarUser')
            ->where([
                'googleCalendarId' => $googleCalendarEntityId,
                'active' => true,
            ])
            ->findOne();

        if (!$gcUser) {
            return null;
        }

        $userId = $gcUser->get('userId');

        return is_string($userId) && $userId !== '' ? $userId : null;
    }

    /**
     * @param array<string, mixed> $googleData
     */
    private function deleteGoogleEventWithFallback(array $googleData, ?string $fallbackUserId): bool
    {
        $deleteUserIds = array_values(array_unique(array_filter([
            $this->resolveCalendarOwnerUserId($googleData),
            $fallbackUserId,
        ])));

        foreach ($deleteUserIds as $deleteUserId) {
            if ($this->deleteGoogleEventForUser($deleteUserId, $googleData)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $googleData
     */
    private function deleteGoogleEventForUser(string $userId, array $googleData): bool
    {
        if (!$this->isGoogleCalendarApiAvailableForUser($userId)) {
            return false;
        }

        $googleCalendar = $this->resolveGoogleCalendarEntity($googleData);

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
        if (!$this->isGoogleCalendarApiAvailableForUser($userId)) {
            return false;
        }

        $externalAccount = $this->entityManager->getEntityById('ExternalAccount', 'Google__' . $userId);

        if (!$externalAccount) {
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

    private function isGoogleCalendarApiAvailableForUser(string $userId): bool
    {
        $externalAccount = $this->entityManager->getEntityById('ExternalAccount', 'Google__' . $userId);

        if (!$externalAccount || !$externalAccount->get('enabled')) {
            return false;
        }

        return (bool) ($externalAccount->get('calendarEnabled') || $externalAccount->get('googleCalendarEnabled'));
    }

    /**
     * @param array<string, mixed> $googleData
     */
    private function resolveGoogleCalendarEntity(array $googleData): ?Entity
    {
        $storedId = $googleData['googleCalendarId'] ?? null;

        if (!is_string($storedId) || $storedId === '') {
            return null;
        }

        $googleCalendar = $this->entityManager->getEntityById('GoogleCalendar', $storedId);

        if ($googleCalendar) {
            return $googleCalendar;
        }

        return $this->getGoogleRepository()->getCalendarByGCId($storedId);
    }

    private function resolveMainGoogleCalendarIdForUser(string $userId): ?string
    {
        $gcUser = $this->getGoogleRepository()->getUsersMainCalendar($userId);

        if (!$gcUser) {
            return null;
        }

        $googleCalendar = $this->entityManager
            ->getRDBRepository('GoogleCalendar')
            ->getById($gcUser->get('googleCalendarId'));

        if (!$googleCalendar) {
            return null;
        }

        $calendarId = $googleCalendar->get('calendarId');

        return is_string($calendarId) && $calendarId !== '' ? $calendarId : null;
    }

    private function buildGoogleSearchNeedle(Entity $entity): ?string
    {
        $name = trim((string) ($entity->get('name') ?? ''));

        if ($name === '') {
            return null;
        }

        if (preg_match('/^(\d{3,6})\b/', $name, $matches)) {
            return $matches[1];
        }

        $compact = preg_replace('/\s+/', ' ', $name) ?? $name;

        return strlen($compact) > 48 ? substr($compact, 0, 48) : $compact;
    }

    private function googleSummaryMatchesAppointment(string $summary, Entity $entity, string $needle): bool
    {
        if ($needle !== '' && str_contains($summary, $needle)) {
            return true;
        }

        $name = trim((string) ($entity->get('name') ?? ''));

        if ($name !== '' && str_contains($summary, $name)) {
            return true;
        }

        $dateStart = $entity->get('dateStart');

        if (!is_string($dateStart) || strlen($dateStart) < 16) {
            return false;
        }

        $timePart = substr($dateStart, 11, 5);

        return $timePart !== '' && str_contains($summary, $timePart);
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

    private function pushEntityToGoogle(Entity $entity, string $userId): bool
    {
        if (!$this->isCalendarPushEnabledForUser($userId)) {
            return false;
        }

        $eventAction = $this->createGoogleEventAction($userId);

        if ($eventAction === null) {
            return false;
        }

        try {
            return (bool) $eventAction->insertIntoGoogle($this->buildEspoEventPayload($entity));
        } catch (\Throwable $e) {
            $GLOBALS['log']->error(
                'AppuntamentoGoogleSync: push failed for ' . $entity->getId() . ': ' . $e->getMessage()
            );

            return false;
        }
    }

    private function createGoogleEventAction(string $userId): ?GoogleEventAction
    {
        $gcUser = $this->getGoogleRepository()->getUsersMainCalendar($userId);

        if (!$gcUser) {
            return null;
        }

        $googleCalendar = $this->entityManager
            ->getRDBRepository('GoogleCalendar')
            ->getById($gcUser->get('googleCalendarId'));

        if (!$googleCalendar) {
            return null;
        }

        $calendarId = $googleCalendar->get('calendarId');

        if (!is_string($calendarId) || $calendarId === '') {
            return null;
        }

        $externalAccount = $this->entityManager->getEntityById('ExternalAccount', 'Google__' . $userId);

        if (!$externalAccount) {
            return null;
        }

        $entityLabels = [];
        $syncEntities = $externalAccount->get('calendarEntityTypes') ?? [];

        if (is_array($syncEntities)) {
            foreach ($syncEntities as $syncEntity) {
                $label = $externalAccount->get($syncEntity . 'IdentificationLabel');
                $entityLabels[$syncEntity] = is_string($label) ? $label : '';
            }
        }

        $eventAction = $this->injectableFactory->create(GoogleEventAction::class);
        $eventAction->setUserId($userId);
        $eventAction->setCalendarId($calendarId);
        $eventAction->syncParams = [
            'calendar' => $gcUser,
            'entityLabels' => $entityLabels,
            'dontSyncEventAttendees' => $externalAccount->get('dontSyncEventAttendees'),
        ];

        return $eventAction;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEspoEventPayload(Entity $entity): array
    {
        return [
            'scope' => self::ENTITY_TYPE,
            'id' => $entity->getId(),
            'name' => $entity->get('name'),
            'dateStart' => $entity->get('dateStart'),
            'dateEnd' => $entity->get('dateEnd'),
            'dateStartDate' => null,
            'dateEndDate' => null,
            'modifiedAt' => $entity->get('modifiedAt'),
            'description' => $entity->get('description'),
            'status' => $entity->get('status'),
            'location' => $entity->get('location'),
            'cLocation' => null,
            'uid' => $entity->get('uid'),
            'joinUrl' => $entity->get('joinUrl'),
            'attendees' => [],
            'deleted' => false,
        ];
    }

    private function persistAssignedUserIdIfNeeded(Entity $entity, string $userId): bool
    {
        if ((string) $entity->get('assignedUserId') === $userId) {
            return false;
        }

        $fresh = $this->entityManager->getEntityById(self::ENTITY_TYPE, $entity->getId());

        if (!$fresh) {
            return false;
        }

        $fresh->set('assignedUserId', $userId);
        $this->entityManager->saveEntity($fresh, ['skipHooks' => true]);
        $entity->set('assignedUserId', $userId);

        return true;
    }

    private function getGoogleRepository(): GoogleCalendarRepository
    {
        /** @var GoogleCalendarRepository $repository */
        $repository = $this->entityManager->getRepository('GoogleCalendar');

        return $repository;
    }
}
