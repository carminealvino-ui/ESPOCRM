<?php

namespace Espo\Custom\Services;

use Espo\Core\ExternalAccount\ClientManager;
use Espo\Core\InjectableFactory;
use Espo\Modules\Google\Core\Google\Actions\Event as GoogleEventAction;
use Espo\Modules\Google\Repositories\GoogleCalendar as GoogleCalendarRepository;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Query\Part\UpdateBuilder;

/**
 * Sync Appuntamento ↔ Google Calendar (rimozione su Not Held / delete / cambio consulente).
 */
class AppuntamentoGoogleSync
{
    private const ENTITY_TYPE = 'Appuntamento';

    private const GHOST_NAME_MARKER = '(APPUNTAMENTO SENZA PROSPECT)';

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

        if (!$this->isSyncConGoogleEnabled($entity)) {
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

    /**
     * Flag "Sync con Google" disattivato → rimuovi evento; attivato → push in afterSave.
     */
    public function handleSyncConGoogleToggle(Entity $entity): void
    {
        if ($entity->isNew() || $entity->getEntityType() !== self::ENTITY_TYPE) {
            return;
        }

        $wasEnabled = (bool) $entity->getFetched('syncConGoogle');
        $isEnabled = $this->isSyncConGoogleEnabled($entity);

        if ($wasEnabled === $isEnabled) {
            return;
        }

        if ($isEnabled) {
            return;
        }

        $calendarUserId = $this->resolveGoogleCalendarUserIdForEntity($entity, true);
        $this->unlinkFromGoogleForUser($entity, $calendarUserId);

        if ($calendarUserId !== null) {
            $this->bonificaDeleteOrphanGoogleEvent($entity, $calendarUserId);
        }
    }

    public function handleNotHeldStatus(Entity $entity): void
    {
        if ($entity->getEntityType() !== self::ENTITY_TYPE) {
            return;
        }

        if ($entity->get('status') !== 'Not Held') {
            return;
        }

        $calendarUserId = $this->resolveGoogleCalendarUserIdForEntity($entity, true);

        if ($calendarUserId === null) {
            return;
        }

        if ($this->hasGoogleLink($entity->getId())) {
            $fallbackUserId = $this->resolvePrimaryUserId(
                $entity->getFetched('assignedUsersIds'),
                $entity->getFetched('assignedUserId')
            );

            $this->unlinkFromGoogleForUser($entity, $fallbackUserId ?? $calendarUserId);
        }

        $this->bonificaDeleteOrphanGoogleEvent($entity, $calendarUserId);
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

        $this->loadAssignedUsersIds($entity);
        $userId = $this->resolveGoogleCalendarUserIdForEntity($entity, false);

        if ($userId === null) {
            return false;
        }

        $this->persistAssignedUserIdIfNeeded($entity, $userId);

        if ($this->hasGoogleLink($entity->getId())) {
            if ($this->isGoogleEventAlive($entity, $userId)) {
                return false;
            }

            $this->getGoogleRepository()->resetEventRelation(self::ENTITY_TYPE, $entity->getId());
        }

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
     * @return 'pushed'|'skipped'|'failed'|'repaired'
     */
    public function bonificaPushMissing(Entity $entity, string $calendarUserId): string
    {
        if (!$this->shouldStayOnGoogleCalendar($entity)) {
            return 'skipped';
        }

        $this->persistAssignedUserIdIfNeeded($entity, $calendarUserId);

        if ($this->hasGoogleLink($entity->getId())) {
            if ($this->isGoogleEventAlive($entity, $calendarUserId)) {
                return 'skipped';
            }

            $this->getGoogleRepository()->resetEventRelation(self::ENTITY_TYPE, $entity->getId());
        }

        if (!$this->pushEntityToGoogle($entity, $calendarUserId)) {
            return 'failed';
        }

        return $this->hasGoogleLink($entity->getId()) ? 'pushed' : 'failed';
    }

    public function describePushSkipReason(Entity $entity, string $calendarUserId): ?string
    {
        if ($this->isGhostAppointment($entity)) {
            return 'ghost duplicato (senza prospect)';
        }

        if (!$this->isSyncConGoogleEnabled($entity)) {
            return 'sync con Google disattivato';
        }

        if (!$this->shouldStayOnGoogleCalendar($entity)) {
            return 'status non sincronizzabile';
        }

        if (
            $this->hasGoogleLink($entity->getId())
            && $this->isGoogleEventAlive($entity, $calendarUserId)
        ) {
            return 'già presente su Google';
        }

        if (!$this->isGoogleCalendarApiAvailableForUser($calendarUserId)) {
            return 'Google API non disponibile';
        }

        if ($this->createGoogleEventAction($calendarUserId) === null) {
            return 'calendario principale Google non configurato';
        }

        return null;
    }

    public function isGoogleEventAlive(Entity $entity, string $calendarUserId): bool
    {
        $entityId = $entity->getId();

        if (!$entityId) {
            return false;
        }

        $googleData = $this->getGoogleRepository()->getEventEntityGoogleData(self::ENTITY_TYPE, $entityId);

        if (!is_array($googleData) || empty($googleData['googleCalendarEventId'])) {
            return false;
        }

        $googleEventId = (string) $googleData['googleCalendarEventId'];

        if ($googleEventId === '' || $googleEventId === 'FAIL') {
            return false;
        }

        $calendarId = $this->resolveMainGoogleCalendarIdForUser($calendarUserId);

        if ($calendarId === null) {
            return false;
        }

        try {
            $client = $this->clientManager->create('Google', $calendarUserId);
            $event = $client->getCalendarClient()->retrieveEvent($calendarId, $googleEventId);
        } catch (\Throwable) {
            return false;
        }

        if (!is_array($event) || empty($event['id'])) {
            return false;
        }

        return ($event['status'] ?? 'confirmed') !== 'cancelled';
    }

    public function isOnConsultantCalendar(Entity $entity, string $consultantUserId): bool
    {
        $this->loadAssignedUsersIds($entity);

        if ($this->hasConsultantAssigned($entity, $consultantUserId)) {
            return true;
        }

        return (string) $entity->get('assignedUserId') === $consultantUserId;
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

        $userTz = $this->resolveUserTimeZone($calendarUserId);

        try {
            $start = new \DateTime($dateStart, $userTz);
        } catch (\Throwable) {
            return false;
        }

        $dayStart = (clone $start)->setTime(0, 0, 0)->setTimezone(new \DateTimeZone('UTC'));
        $dayEnd = (clone $start)->setTime(23, 59, 59)->setTimezone(new \DateTimeZone('UTC'));

        try {
            $client = $this->clientManager->create('Google', $calendarUserId);
            $response = $client->getCalendarClient()->getEventList($calendarId, [
                'timeMin' => $dayStart->format('Y-m-d\TH:i:s\Z'),
                'timeMax' => $dayEnd->format('Y-m-d\TH:i:s\Z'),
                'singleEvents' => 'true',
                'maxResults' => 100,
                'q' => $needle,
            ]);
        } catch (\Throwable $e) {
            $GLOBALS['log']->error(
                'AppuntamentoGoogleSync: orphan search failed for user ' . $calendarUserId . ': ' . $e->getMessage()
            );

            return false;
        }

        if (!is_array($response) || !is_array($response['items'] ?? null)) {
            return false;
        }

        if ($response['items'] === []) {
            $response = $client->getCalendarClient()->getEventList($calendarId, [
                'timeMin' => $dayStart->format('Y-m-d\TH:i:s\Z'),
                'timeMax' => $dayEnd->format('Y-m-d\TH:i:s\Z'),
                'singleEvents' => 'true',
                'maxResults' => 100,
            ]);
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

    /**
     * Scansiona Google Calendar e rimuove eventi il cui appuntamento Espo è Not Held / eliminato.
     *
     * @return int numero eventi rimossi
     */
    /**
     * @return array{removed: int, scanned: int, candidates: int}
     */
    public function bonificaReconcileGoogleRange(
        string $calendarUserId,
        string $fromDate,
        string $toDate,
        bool $apply = true
    ): array {
        $empty = ['removed' => 0, 'scanned' => 0, 'candidates' => 0];

        if (!$this->isGoogleCalendarApiAvailableForUser($calendarUserId)) {
            return $empty;
        }

        $calendarId = $this->resolveMainGoogleCalendarIdForUser($calendarUserId);

        if ($calendarId === null) {
            return $empty;
        }

        $userTz = $this->resolveUserTimeZone($calendarUserId);

        try {
            $rangeStart = new \DateTime($fromDate . ' 00:00:00', $userTz);
            $rangeEnd = new \DateTime($toDate . ' 23:59:59', $userTz);
        } catch (\Throwable) {
            return $empty;
        }

        $timeMin = (clone $rangeStart)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
        $timeMax = (clone $rangeEnd)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');

        try {
            $client = $this->clientManager->create('Google', $calendarUserId);
            $response = $client->getCalendarClient()->getEventList($calendarId, [
                'timeMin' => $timeMin,
                'timeMax' => $timeMax,
                'singleEvents' => 'true',
                'maxResults' => 250,
            ]);
        } catch (\Throwable $e) {
            $GLOBALS['log']->error('AppuntamentoGoogleSync: reconcile list failed: ' . $e->getMessage());

            return $empty;
        }

        if (!is_array($response) || !is_array($response['items'] ?? null)) {
            return $empty;
        }

        $removed = 0;
        $scanned = 0;
        $candidates = 0;

        foreach ($response['items'] as $item) {
            if (!is_array($item) || empty($item['id'])) {
                continue;
            }

            $summary = (string) ($item['summary'] ?? '');

            if ($summary === '' || str_starts_with($summary, 'Too Good To Go')) {
                continue;
            }

            if (!$this->looksLikeAppuntamentoGoogleEvent($summary)) {
                continue;
            }

            $scanned++;
            $appointment = $this->findAppointmentForGoogleEvent($summary, $item, $userTz);

            if ($appointment === null || $this->shouldStayOnGoogleCalendar($appointment)) {
                continue;
            }

            $candidates++;

            if (!$apply) {
                continue;
            }

            try {
                if ($client->getCalendarClient()->deleteEvent($calendarId, (string) $item['id'])) {
                    $removed++;
                }
            } catch (\Throwable $e) {
                $GLOBALS['log']->error(
                    'AppuntamentoGoogleSync: reconcile delete ' . $item['id'] . ': ' . $e->getMessage()
                );
            }
        }

        return [
            'removed' => $removed,
            'scanned' => $scanned,
            'candidates' => $candidates,
        ];
    }

    public function isSyncConGoogleEnabled(Entity $entity): bool
    {
        return (bool) $entity->get('syncConGoogle');
    }

    public function shouldStayOnGoogleCalendar(Entity $entity): bool
    {
        if ($entity->getEntityType() !== self::ENTITY_TYPE) {
            return false;
        }

        if ($entity->get('deleted')) {
            return false;
        }

        if (!$this->isSyncConGoogleEnabled($entity)) {
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

    /**
     * Allinea il flag syncConGoogle agli appuntamenti già presenti su Google (migrazione).
     */
    public function bonificaBackfillSyncConGoogleFlag(string $sinceDate): int
    {
        $updated = 0;

        $appointments = $this->entityManager
            ->getRDBRepository(self::ENTITY_TYPE)
            ->where([
                'deleted' => false,
                'syncConGoogle' => false,
                'status' => ['Planned', 'Held', 'Ingestibile'],
                'dateStart>=' => $sinceDate . ' 00:00:00',
            ])
            ->find();

        foreach ($appointments as $appointment) {
            if ($this->isGhostAppointment($appointment) || !$this->hasGoogleLink($appointment->getId())) {
                continue;
            }

            $appointment->set('syncConGoogle', true);
            $this->entityManager->saveEntity($appointment, ['skipHooks' => true]);
            $updated++;
        }

        return $updated;
    }

    public function isGhostAppointment(Entity $entity): bool
    {
        if ($this->buildIdentityKey($entity) !== null) {
            return false;
        }

        $name = (string) ($entity->get('name') ?? '');

        return str_contains($name, self::GHOST_NAME_MARKER);
    }

    /**
     * Rimuove appuntamenti ghost "(APPUNTAMENTO SENZA PROSPECT)" senza prospect/parent.
     * Include duplicati nello stesso slot e orphan Planned rimasti fuori sync/Google.
     */
    public function bonificaPurgeSlotGhosts(?string $sinceDate = null): int
    {
        $where = [
            'deleted' => false,
            'name*' => '%' . self::GHOST_NAME_MARKER . '%',
        ];

        if (is_string($sinceDate) && $sinceDate !== '') {
            $where['dateStart>='] = $sinceDate . ' 00:00:00';
        }

        $ghosts = $this->entityManager
            ->getRDBRepository(self::ENTITY_TYPE)
            ->where($where)
            ->find();

        $purged = 0;

        foreach ($ghosts as $ghost) {
            if (!$this->isGhostAppointment($ghost)) {
                continue;
            }

            $start = $ghost->get('dateStart');
            $end = $ghost->get('dateEnd');
            $status = (string) ($ghost->get('status') ?? '');

            $isDuplicateInSlot = is_string($start) && is_string($end) && $start !== '' && $end !== ''
                && $this->slotHasRealAppointment($start, $end, $ghost->getId());

            // Planned orphan: mai validi in produzione (restano fuori da Google).
            $isOrphanPlanned = $status === 'Planned';

            if (!$isDuplicateInSlot && !$isOrphanPlanned) {
                continue;
            }

            $this->getGoogleRepository()->resetEventRelation(self::ENTITY_TYPE, $ghost->getId());
            $this->entityManager->removeEntity($ghost, [
                'skipHooks' => true,
                'silent' => true,
            ]);
            $purged++;
        }

        return $purged;
    }

    private function slotHasRealAppointment(string $dateStart, string $dateEnd, ?string $excludeId): bool
    {
        $where = [
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
            'deleted' => false,
        ];

        if ($excludeId) {
            $where['id!='] = $excludeId;
        }

        foreach ($this->entityManager->getRDBRepository(self::ENTITY_TYPE)->where($where)->find() as $other) {
            if (!$this->isGhostAppointment($other)) {
                return true;
            }
        }

        return false;
    }

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

        $GLOBALS['log']->warning(
            'AppuntamentoGoogleSync: Google delete failed, link kept for retry. Appuntamento='
            . $entityId
            . ' user=' . ($syncUserId ?? 'null')
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

    private function persistAssignedUserIdIfNeeded(Entity $entity, string $userId): bool
    {
        if ((string) $entity->get('assignedUserId') === $userId) {
            return false;
        }

        $entityId = $entity->getId();

        if (!$entityId) {
            return false;
        }

        // UPDATE diretto: evita hook Google (dummy "APPUNTAMENTO SENZA PROSPECT").
        $this->entityManager->getQueryExecutor()->execute(
            UpdateBuilder::create()
                ->in(self::ENTITY_TYPE)
                ->set(['assignedUserId' => $userId])
                ->where(['id' => $entityId])
                ->build()
        );

        $entity->set('assignedUserId', $userId);

        return true;
    }

    public function hasGoogleLink(string $entityId): bool
    {
        $googleData = $this->getGoogleRepository()->getEventEntityGoogleData(self::ENTITY_TYPE, $entityId);

        return is_array($googleData) && !empty($googleData['googleCalendarEventId']);
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
        $dateStart = $entity->get('dateStart');
        $timePart = is_string($dateStart) && strlen($dateStart) >= 16 ? substr($dateStart, 11, 5) : '';
        $name = trim((string) ($entity->get('name') ?? ''));

        $codeMatch = $needle !== '' && str_contains($summary, $needle);
        $nameMatch = $name !== '' && str_contains($summary, $name);
        $timeMatch = $timePart !== '' && str_contains($summary, $timePart);

        if ($codeMatch && $timeMatch) {
            return true;
        }

        if ($nameMatch && $timeMatch) {
            return true;
        }

        if ($nameMatch && $name !== '' && strlen($name) > 20) {
            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $googleItem
     */
    private function findAppointmentForGoogleEvent(string $summary, array $googleItem, \DateTimeZone $userTz): ?Entity
    {
        $code = null;

        if (preg_match('/(?:^|\s)(\d{5})\s*-/', $summary, $matches)) {
            $code = $matches[1];
        } elseif (preg_match('/\b(\d{5})\b/', $summary, $matches)) {
            $code = $matches[1];
        }

        $eventStart = $googleItem['start']['dateTime'] ?? $googleItem['start']['date'] ?? null;

        if (!is_string($eventStart) || $eventStart === '') {
            return null;
        }

        try {
            $eventDt = new \DateTime($eventStart);
            $eventDt->setTimezone($userTz);
        } catch (\Throwable) {
            return null;
        }

        $day = $eventDt->format('Y-m-d');
        $where = [
            'deleted' => false,
            'dateStart>=' => $day . ' 00:00:00',
            'dateStart<=' => $day . ' 23:59:59',
        ];

        if ($code !== null) {
            $where['name*'] = $code . '%';
        } else {
            $where['name*'] = '%' . substr($summary, 0, 32) . '%';
        }

        $candidates = $this->entityManager
            ->getRDBRepository(self::ENTITY_TYPE)
            ->where($where)
            ->find();

        $eventMinutes = (int) $eventDt->format('H') * 60 + (int) $eventDt->format('i');

        foreach ($candidates as $candidate) {
            $start = $candidate->get('dateStart');

            if (!is_string($start) || strlen($start) < 16) {
                continue;
            }

            try {
                $appDt = new \DateTime($start, $userTz);
            } catch (\Throwable) {
                continue;
            }

            $appMinutes = (int) $appDt->format('H') * 60 + (int) $appDt->format('i');

            if (abs($appMinutes - $eventMinutes) <= 5) {
                return $candidate;
            }
        }

        return null;
    }

    private function resolveGoogleCalendarUserIdForEntity(Entity $entity, bool $preferFetched): ?string
    {
        $adminIds = $this->resolveAdminUserIds();
        $candidates = [];

        if ($preferFetched) {
            $candidates[] = $this->resolvePrimaryUserId(
                $entity->getFetched('assignedUsersIds'),
                $entity->getFetched('assignedUserId')
            );
        }

        $this->loadAssignedUsersIds($entity);
        $candidates[] = $this->resolvePrimaryUserId(
            $entity->get('assignedUsersIds'),
            $entity->get('assignedUserId')
        );

        foreach ($candidates as $userId) {
            if (
                is_string($userId)
                && $userId !== ''
                && !in_array($userId, $adminIds, true)
                && $this->isGoogleCalendarApiAvailableForUser($userId)
            ) {
                return $userId;
            }
        }

        $entityId = $entity->getId();

        if ($entityId) {
            $googleData = $this->getGoogleRepository()->getEventEntityGoogleData(self::ENTITY_TYPE, $entityId);

            if (is_array($googleData)) {
                $ownerId = $this->resolveCalendarOwnerUserId($googleData);

                if ($ownerId !== null) {
                    return $ownerId;
                }
            }
        }

        foreach ($candidates as $userId) {
            if (is_string($userId) && $userId !== '' && $this->isGoogleCalendarApiAvailableForUser($userId)) {
                return $userId;
            }
        }

        return null;
    }

    private function looksLikeAppuntamentoGoogleEvent(string $summary): bool
    {
        if (preg_match('/(?:^|\s)(\d{5})\s*-/', $summary)) {
            return true;
        }

        return str_contains($summary, 'LZ/');
    }

    private function resolveUserTimeZone(string $userId): \DateTimeZone
    {
        $preferences = $this->entityManager->getEntityById('Preferences', $userId);
        $timeZone = $preferences?->get('timeZone');

        try {
            return new \DateTimeZone(is_string($timeZone) && $timeZone !== '' ? $timeZone : 'Europe/Rome');
        } catch (\Throwable) {
            return new \DateTimeZone('Europe/Rome');
        }
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
        if (!$this->isGoogleCalendarApiAvailableForUser($userId)) {
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

    private function getGoogleRepository(): GoogleCalendarRepository
    {
        /** @var GoogleCalendarRepository $repository */
        $repository = $this->entityManager->getRepository('GoogleCalendar');

        return $repository;
    }
}
