<?php

namespace Espo\Custom\Services;

use Espo\Core\Utils\Log;
use Espo\Custom\Services\CallStandardTesto;
use Espo\Custom\Tools\Appuntamento\PendingCallDateTime;
use Espo\Custom\Tools\DateTime\BusinessDateTime;
use Espo\Modules\Crm\Entities\Reminder;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class AppuntamentoPendingCallCreator
{
    private const NOTA_PREFIX = 'Auto-Pending-Appuntamento:';
    private const NOTA_RICHIAMO_PREFIX = 'Auto-Richiamo-Appuntamento:';
    private const NOTA_RICHIAMO_CALL_PREFIX = 'Auto-Richiamo-Call:';
    private const TIPOLOGIA = 'Richiamo su Opportunità Generata';
    private const ADMIN_USER_ID = '1';
    private const REMINDER_SECONDS = 0;

    /** @var array<string, string> */
    private static array $rememberedLeadIds = [];

    public function __construct(
        private EntityManager $entityManager,
        private Log $log,
        private ?CallStandardTesto $standardTesto = null,
    ) {}

    private function getStandardTesto(): string
    {
        if ($this->standardTesto) {
            return $this->standardTesto->get();
        }

        return CallStandardTesto::DEFAULT;
    }

    public static function rememberLeadId(string $appuntamentoId, string $leadId): void
    {
        if ($appuntamentoId === '' || $leadId === '') {
            return;
        }

        self::$rememberedLeadIds[$appuntamentoId] = $leadId;
    }

    public function createIfNeeded(
        Entity $appuntamento,
        ?\DateTimeImmutable $notBefore = null,
        ?string $leadIdOverride = null
    ): ?string {
        if ($appuntamento->get('status') !== 'Held') {
            return null;
        }

        if ($appuntamento->get('sottostato') !== 'Pending') {
            return null;
        }

        if (!PendingCallDateTime::isAppointmentEligible($appuntamento->get('dateStart'))) {
            return null;
        }

        $appuntamentoId = $appuntamento->getId();

        if (!$appuntamentoId) {
            return null;
        }

        $existingCallId = $this->findExistingCallId($appuntamentoId);

        if ($existingCallId) {
            $this->syncCallOwnerFromAppuntamento($existingCallId, $appuntamento);
            $existingCall = $this->entityManager->getEntityById('Call', $existingCallId);

            if ($existingCall) {
                $this->syncPopupReminders($existingCall);
            }

            return $existingCallId;
        }

        $leadId = $leadIdOverride ?: $this->resolveLeadId($appuntamento);

        if (!$leadId) {
            $this->log->warning(
                'Auto-create Call Pending: Lead non trovato per Appuntamento {id} (parentType={parentType}, parentId={parentId}, prospectId={prospectId})',
                [
                    'id' => $appuntamentoId,
                    'parentType' => (string) $appuntamento->get('parentType'),
                    'parentId' => (string) $appuntamento->get('parentId'),
                    'prospectId' => (string) $appuntamento->get('prospectId'),
                ]
            );

            return null;
        }

        $lead = $this->entityManager->getEntityById('Lead', $leadId);

        if (!$lead) {
            $this->log->warning(
                'Auto-create Call Pending: Lead {leadId} inesistente per Appuntamento {id}',
                [
                    'id' => $appuntamentoId,
                    'leadId' => $leadId,
                ]
            );

            return null;
        }

        unset(self::$rememberedLeadIds[$appuntamentoId]);

        $callInstant = $this->buildCallInstantFromAppointment($appuntamento, $notBefore);

        if (!$callInstant) {
            return null;
        }

        $callDateStartUtc = BusinessDateTime::businessToStorage($callInstant);

        $parentName = $appuntamento->get('parentName') ?: $lead->get('name');
        $telefono = $appuntamento->get('telefono') ?: $lead->get('phoneNumber');
        $presentation = $this->buildCallPresentationFields($callInstant, $parentName, $telefono);
        $ownerUserId = $this->resolveOwnerUserId($appuntamento);
        $ownerUserName = $this->resolveOwnerUserName($ownerUserId);

        $call = $this->entityManager->createEntity('Call');

        $usersNames = [];

        if ($ownerUserId && $ownerUserName) {
            $usersNames[$ownerUserId] = $ownerUserName;
        }

        $call->set(array_merge($presentation, [
            'status' => 'Planned',
            'direction' => 'Outbound',
            'tipologia' => self::TIPOLOGIA,
            'richiamo' => self::TIPOLOGIA,
            'parentType' => 'Lead',
            'parentId' => $leadId,
            'parentName' => $parentName,
            'prospectId' => $appuntamento->get('prospectId'),
            'prospectName' => $appuntamento->get('prospectName'),
            'telefono' => $telefono,
            'dateStart' => $callDateStartUtc,
            'assignedUserId' => $ownerUserId,
            'assignedUserName' => $ownerUserName,
            'usersIds' => $ownerUserId ? [$ownerUserId] : [],
            'usersNames' => $usersNames,
            'daRichiamare' => false,
            'whatsApp' => true,
            'vocale' => false,
            'testo' => $this->getStandardTesto(),
            'nota' => $this->buildNota($appuntamentoId, $appuntamento->get('dateStart')),
        ]));

        // skipHooks: bypass formula Call; dateStart già in UTC. Promemoria creati a mano sotto.
        $this->entityManager->saveEntity($call, [
            'skipAcl' => true,
            'silent' => true,
            'skipHooks' => true,
        ]);

        $this->syncPopupReminders($call);

        $this->log->info(
            'Auto-create Call Pending: creata Call {callId} per Appuntamento {id} UTC {dateStartUtc} (Rome {dateStartRome})',
            [
                'callId' => $call->getId(),
                'id' => $appuntamentoId,
                'dateStartUtc' => $callDateStartUtc,
                'dateStartRome' => PendingCallDateTime::formatBusinessDateTime($callInstant),
            ]
        );

        return $call->getId();
    }

    public function createRichiamoIfNeeded(Entity $appuntamento): ?string
    {
        if (!$appuntamento->get('daRichiamare')) {
            return null;
        }

        $dataRichiamo = $appuntamento->get('dataRichiamo');
        $tipologia = trim((string) $appuntamento->get('richiamo'));

        if (!$dataRichiamo || $tipologia === '') {
            return null;
        }

        $appuntamentoId = $appuntamento->getId();

        if (!$appuntamentoId) {
            return null;
        }

        $existingCallId = $this->findPlannedRichiamoCallIdByAppuntamento($appuntamentoId);

        if ($existingCallId) {
            $existingCall = $this->entityManager->getEntityById('Call', $existingCallId);

            if ($existingCall && (string) $existingCall->get('dateStart') !== $dataRichiamo) {
                $this->applyRinvioToStoredCall($existingCall, $dataRichiamo, $tipologia);
            } else {
                $this->syncCallOwnerFromAppuntamento($existingCallId, $appuntamento);

                if ($existingCall) {
                    $this->syncPopupReminders($existingCall);
                }
            }

            return $existingCallId;
        }

        $leadId = $this->resolveLeadId($appuntamento);

        if (!$leadId) {
            return null;
        }

        $lead = $this->entityManager->getEntityById('Lead', $leadId);

        if (!$lead) {
            return null;
        }

        $callInstant = BusinessDateTime::storageToBusiness($dataRichiamo);
        $callDateStartUtc = BusinessDateTime::businessToStorage($callInstant);
        $parentName = $appuntamento->get('parentName') ?: $lead->get('name');
        $telefono = $appuntamento->get('telefono') ?: $lead->get('phoneNumber');
        $presentation = $this->buildCallPresentationFields(
            $callInstant,
            $parentName,
            $telefono,
            $tipologia
        );
        $ownerUserId = $this->resolveOwnerUserId($appuntamento);
        $ownerUserName = $this->resolveOwnerUserName($ownerUserId);

        $call = $this->entityManager->createEntity('Call');

        $usersNames = [];

        if ($ownerUserId && $ownerUserName) {
            $usersNames[$ownerUserId] = $ownerUserName;
        }

        $call->set(array_merge($presentation, [
            'status' => 'Planned',
            'direction' => 'Outbound',
            'tipologia' => $tipologia,
            'richiamo' => $tipologia,
            'parentType' => 'Lead',
            'parentId' => $leadId,
            'parentName' => $parentName,
            'prospectId' => $appuntamento->get('prospectId'),
            'prospectName' => $appuntamento->get('prospectName'),
            'telefono' => $telefono,
            'dateStart' => $callDateStartUtc,
            'assignedUserId' => $ownerUserId,
            'assignedUserName' => $ownerUserName,
            'usersIds' => $ownerUserId ? [$ownerUserId] : [],
            'usersNames' => $usersNames,
            'daRichiamare' => false,
            'whatsApp' => true,
            'vocale' => false,
            'testo' => $this->getStandardTesto(),
            'nota' => $this->buildRichiamoNota($appuntamentoId, $dataRichiamo),
        ]));

        $this->entityManager->saveEntity($call, [
            'skipAcl' => true,
            'silent' => true,
            'skipHooks' => true,
        ]);

        $this->syncPopupReminders($call);

        $this->log->info(
            'Auto-create Call richiamo: creata Call {callId} per Appuntamento {id} UTC {dateStartUtc}',
            [
                'callId' => $call->getId(),
                'id' => $appuntamentoId,
                'dateStartUtc' => $callDateStartUtc,
            ]
        );

        return $call->getId();
    }

    /**
     * Rinvio richiamo ancora Pianificato: sposta dateStart alla nuova dataRichiamo.
     */
    public function applyRinvioToEntity(Entity $call): bool
    {
        if ($call->getEntityType() !== 'Call' || (string) $call->get('status') !== 'Planned') {
            return false;
        }

        if (!$call->get('daRichiamare') || !$call->get('dataRichiamo')) {
            return false;
        }

        $dataRichiamo = (string) $call->get('dataRichiamo');
        $previousDateStart = (string) ($call->getFetched('dateStart') ?: $call->get('dateStart'));

        if ($dataRichiamo === $previousDateStart && !$call->isAttributeChanged('dataRichiamo')) {
            $call->set([
                'daRichiamare' => false,
                'dataRichiamo' => null,
            ]);

            return false;
        }

        $tipologia = trim((string) ($call->get('richiamo') ?: $call->get('tipologia')));

        if ($tipologia === '') {
            return false;
        }

        $callInstant = BusinessDateTime::storageToBusiness($dataRichiamo);
        $presentation = $this->buildCallPresentationFields(
            $callInstant,
            $call->get('parentName'),
            $call->get('telefono'),
            $tipologia
        );

        $nota = trim((string) $call->get('nota'));
        $rinvioLine = $this->buildRinvioNotaLine($previousDateStart, $dataRichiamo);

        $call->set(array_merge($presentation, [
            'dateStart' => BusinessDateTime::businessToStorage($callInstant),
            'tipologia' => $tipologia,
            'richiamo' => $tipologia,
            'daRichiamare' => false,
            'dataRichiamo' => null,
            'nota' => $nota !== '' ? $nota . "\n" . $rinvioLine : $rinvioLine,
        ]));

        return true;
    }

    /**
     * Dopo esito Svolto/Non svolto con flag rinvio: crea nuova Call pianificata.
     */
    public function createFollowUpFromCall(Entity $sourceCall): ?string
    {
        if (!$sourceCall->get('daRichiamare')) {
            return null;
        }

        $dataRichiamo = $sourceCall->get('dataRichiamo');
        $tipologia = trim((string) ($sourceCall->get('richiamo') ?: $sourceCall->get('tipologia')));

        if (!$dataRichiamo || $tipologia === '') {
            return null;
        }

        $status = (string) $sourceCall->get('status');

        if (!in_array($status, ['Held', 'Not Held'], true)) {
            return null;
        }

        $sourceCallId = $sourceCall->getId();

        if (!$sourceCallId) {
            return null;
        }

        $existingCallId = $this->findPlannedFollowUpCallId($sourceCallId);

        if ($existingCallId) {
            $existingCall = $this->entityManager->getEntityById('Call', $existingCallId);

            if ($existingCall && (string) $existingCall->get('dateStart') !== $dataRichiamo) {
                $this->applyRinvioToStoredCall($existingCall, $dataRichiamo, $tipologia);
            } elseif ($existingCall) {
                $this->syncPopupReminders($existingCall);
            }

            return $existingCallId;
        }

        $callInstant = BusinessDateTime::storageToBusiness($dataRichiamo);
        $callDateStartUtc = BusinessDateTime::businessToStorage($callInstant);
        $parentName = $sourceCall->get('parentName');
        $telefono = $sourceCall->get('telefono');
        $presentation = $this->buildCallPresentationFields(
            $callInstant,
            $parentName,
            $telefono,
            $tipologia
        );

        $call = $this->entityManager->createEntity('Call');

        $ownerUserId = $sourceCall->get('assignedUserId');
        $ownerUserName = $sourceCall->get('assignedUserName');
        $usersNames = [];

        if ($ownerUserId && $ownerUserName) {
            $usersNames[(string) $ownerUserId] = (string) $ownerUserName;
        }

        $call->set(array_merge($presentation, [
            'status' => 'Planned',
            'direction' => $sourceCall->get('direction') ?: 'Outbound',
            'tipologia' => $tipologia,
            'richiamo' => $tipologia,
            'parentType' => $sourceCall->get('parentType'),
            'parentId' => $sourceCall->get('parentId'),
            'parentName' => $parentName,
            'prospectId' => $sourceCall->get('prospectId'),
            'prospectName' => $sourceCall->get('prospectName'),
            'telefono' => $telefono,
            'dateStart' => $callDateStartUtc,
            'assignedUserId' => $ownerUserId,
            'assignedUserName' => $ownerUserName,
            'usersIds' => $ownerUserId ? [(string) $ownerUserId] : [],
            'usersNames' => $usersNames,
            'daRichiamare' => false,
            'whatsApp' => (bool) $sourceCall->get('whatsApp'),
            'vocale' => (bool) $sourceCall->get('vocale'),
            'testo' => $sourceCall->get('testo') ?: $this->getStandardTesto(),
            'nota' => $this->buildFollowUpCallNota($sourceCallId, $dataRichiamo),
        ]));

        $this->entityManager->saveEntity($call, [
            'skipAcl' => true,
            'silent' => true,
            'skipHooks' => true,
        ]);

        $this->syncPopupReminders($call);

        $this->log->info(
            'Follow-up Call richiamo: creata Call {callId} da Call {sourceId} UTC {dateStartUtc}',
            [
                'callId' => $call->getId(),
                'sourceId' => $sourceCallId,
                'dateStartUtc' => $callDateStartUtc,
            ]
        );

        return $call->getId();
    }

    public function clearRinvioFlagsOnCall(string $callId): void
    {
        $call = $this->entityManager->getEntityById('Call', $callId);

        if (!$call || !$call->get('daRichiamare')) {
            return;
        }

        $call->set([
            'daRichiamare' => false,
            'dataRichiamo' => null,
            'richiamo' => '',
        ]);

        $this->entityManager->saveEntity($call, [
            'skipAcl' => true,
            'silent' => true,
            'skipHooks' => true,
        ]);
    }

    public function resolveOwnerUserId(Entity $appuntamento): ?string
    {
        $appuntamento = $this->reloadAppuntamento($appuntamento);

        $assignedUserIds = $this->resolveAssignedUserIds($appuntamento);

        if ($assignedUserIds !== []) {
            return $this->preferNonAdminUserId($assignedUserIds);
        }

        $modifiedById = $appuntamento->get('modifiedById');

        if ($modifiedById && (string) $modifiedById !== self::ADMIN_USER_ID) {
            return (string) $modifiedById;
        }

        $createdById = $appuntamento->get('createdById');

        if ($createdById && (string) $createdById !== self::ADMIN_USER_ID) {
            return (string) $createdById;
        }

        return $modifiedById ? (string) $modifiedById : ($createdById ? (string) $createdById : null);
    }

    /**
     * @return string[]
     */
    private function resolveAssignedUserIds(Entity $appuntamento): array
    {
        $ids = $appuntamento->getLinkMultipleIdList('assignedUsers');

        if ($ids !== []) {
            return array_values(array_map('strval', $ids));
        }

        $assignedUsersIds = $appuntamento->get('assignedUsersIds');

        if (is_array($assignedUsersIds) && $assignedUsersIds !== []) {
            return array_values(array_map('strval', $assignedUsersIds));
        }

        $assignedUserId = $appuntamento->get('assignedUserId');

        if ($assignedUserId) {
            return [(string) $assignedUserId];
        }

        $collaboratorIds = $appuntamento->getLinkMultipleIdList('collaborators');

        if ($collaboratorIds !== []) {
            return array_values(array_map('strval', $collaboratorIds));
        }

        return [];
    }

    /**
     * @param string[] $userIds
     */
    private function preferNonAdminUserId(array $userIds): string
    {
        foreach ($userIds as $userId) {
            if ((string) $userId !== self::ADMIN_USER_ID) {
                return (string) $userId;
            }
        }

        return (string) $userIds[0];
    }

    private function reloadAppuntamento(Entity $appuntamento): Entity
    {
        $appuntamentoId = $appuntamento->getId();

        if (!$appuntamentoId) {
            return $appuntamento;
        }

        $fresh = $this->entityManager->getEntityById('Appuntamento', $appuntamentoId);

        return $fresh ?: $appuntamento;
    }

    private function syncCallOwnerFromAppuntamento(string $callId, Entity $appuntamento): void
    {
        $ownerUserId = $this->resolveOwnerUserId($appuntamento);

        if (!$ownerUserId) {
            return;
        }

        $call = $this->entityManager->getEntityById('Call', $callId);

        if (!$call) {
            return;
        }

        if ((string) $call->get('assignedUserId') === $ownerUserId) {
            $usersIds = $call->get('usersIds') ?: [];

            if (in_array($ownerUserId, $usersIds, true)) {
                $this->syncPopupReminders($call);

                return;
            }
        }

        $ownerUserName = $this->resolveOwnerUserName($ownerUserId);

        $call->set([
            'assignedUserId' => $ownerUserId,
            'assignedUserName' => $ownerUserName,
            'usersIds' => [$ownerUserId],
            'usersNames' => $ownerUserName ? [$ownerUserId => $ownerUserName] : (object) [],
        ]);

        $this->entityManager->saveEntity($call, [
            'skipAcl' => true,
            'silent' => true,
        ]);

        $this->syncPopupReminders($call);

        $this->log->info(
            'Auto-create Call Pending: aggiornato assegnatario Call {callId} -> user {userId}',
            [
                'callId' => $callId,
                'userId' => $ownerUserId,
            ]
        );
    }

    private function resolveOwnerUserName(?string $userId): ?string
    {
        if (!$userId) {
            return null;
        }

        $user = $this->entityManager->getEntityById('User', $userId);

        return $user ? (string) $user->get('name') : null;
    }

    public function buildExpectedCallDateStartUtc(
        Entity $appuntamento,
        ?\DateTimeImmutable $notBefore = null
    ): ?string {
        $instant = $this->buildCallInstantFromAppointment($appuntamento, $notBefore);

        if (!$instant) {
            return null;
        }

        return BusinessDateTime::businessToStorage($instant);
    }

    public function buildCallInstantFromAppointment(
        Entity $appuntamento,
        ?\DateTimeImmutable $notBefore = null
    ): ?\DateTimeImmutable {
        $dateStart = $appuntamento->get('dateStart');

        if (!$dateStart) {
            return null;
        }

        return PendingCallDateTime::computeCallInstant(
            BusinessDateTime::storageToBusiness($dateStart),
            $notBefore
        );
    }

    /**
     * @return array{name: string, whatsAppNumero: ?string, dateEnd: null}
     */
    public function buildCallPresentationFields(
        \DateTimeImmutable $callInstant,
        ?string $parentName,
        ?string $telefono,
        ?string $tipologia = null
    ): array {
        $dataLabel = PendingCallDateTime::formatBusinessDateTime($callInstant);
        $tipologia = trim((string) ($tipologia ?: self::TIPOLOGIA));

        $parentName = trim((string) $parentName);
        $telefono = trim((string) $telefono);

        $name = mb_strtoupper(
            $dataLabel . ' - ' . $tipologia . ' - ' . $parentName . ' - ' . $telefono,
            'UTF-8'
        );

        $whatsAppNumero = null;

        if ($telefono !== '') {
            $digits = preg_replace('/\D+/', '', $telefono) ?: '';
            $whatsAppNumero = $digits !== '' ? 'https://wa.me/+39' . $digits : null;
        }

        return [
            'name' => $name,
            'whatsAppNumero' => $whatsAppNumero,
            'dateEnd' => null,
        ];
    }

    private function buildNota(string $appuntamentoId, ?string $appointmentDateStart): string
    {
        $lines = [
            self::NOTA_PREFIX . ' ' . $appuntamentoId,
        ];

        if ($appointmentDateStart) {
            $lines[] = 'Richiamo automatico per appuntamento Pending del '
                . BusinessDateTime::formatBusiness($appointmentDateStart, 'd/m/Y H:i');
        }

        return implode("\n", $lines);
    }

    private function buildRichiamoNota(string $appuntamentoId, string $dataRichiamo): string
    {
        return self::NOTA_RICHIAMO_PREFIX . ' ' . $appuntamentoId
            . "\nRichiamo programmato dal riscontro appuntamento per "
            . BusinessDateTime::formatBusiness($dataRichiamo, 'd/m/Y H:i');
    }

    private function resolveLeadId(Entity $appuntamento): ?string
    {
        $appuntamentoId = $appuntamento->getId();

        if ($appuntamentoId && isset(self::$rememberedLeadIds[$appuntamentoId])) {
            return self::$rememberedLeadIds[$appuntamentoId];
        }

        if ($appuntamento->get('parentType') === 'Lead') {
            $parentId = $appuntamento->get('parentId');

            if ($parentId) {
                return $parentId;
            }
        }

        $prospectId = $appuntamento->get('prospectId');

        if (!$prospectId) {
            return null;
        }

        $prospect = $this->entityManager->getEntityById('Prospect', $prospectId);

        if (!$prospect) {
            return null;
        }

        $lead = (new LeadProspectSync($this->entityManager))
            ->findExistingLeadByProspect($prospect);

        return $lead?->getId();
    }

    private function findPlannedRichiamoCallIdByAppuntamento(string $appuntamentoId): ?string
    {
        $existing = $this->entityManager
            ->getRDBRepository('Call')
            ->where([
                'nota*' => self::NOTA_RICHIAMO_PREFIX . ' ' . $appuntamentoId,
                'status' => 'Planned',
            ])
            ->findOne();

        return $existing?->getId();
    }

    private function findPlannedFollowUpCallId(string $sourceCallId): ?string
    {
        $existing = $this->entityManager
            ->getRDBRepository('Call')
            ->where([
                'nota*' => self::NOTA_RICHIAMO_CALL_PREFIX . ' ' . $sourceCallId,
                'status' => 'Planned',
            ])
            ->findOne();

        return $existing?->getId();
    }

    private function applyRinvioToStoredCall(Entity $call, string $dataRichiamo, string $tipologia): void
    {
        $call->set([
            'daRichiamare' => true,
            'dataRichiamo' => $dataRichiamo,
            'richiamo' => $tipologia,
        ]);

        $this->applyRinvioToEntity($call);

        $this->entityManager->saveEntity($call, [
            'skipAcl' => true,
            'silent' => true,
            'skipHooks' => true,
        ]);

        $this->syncPopupReminders($call);
    }

    private function buildRinvioNotaLine(string $previousDateStart, string $newDateStart): string
    {
        $nowUtc = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        return 'Rinviato il '
            . BusinessDateTime::formatBusiness($nowUtc, 'd/m/Y H:i')
            . ' da ' . BusinessDateTime::formatBusiness($previousDateStart, 'd/m/Y H:i')
            . ' a ' . BusinessDateTime::formatBusiness($newDateStart, 'd/m/Y H:i');
    }

    private function buildFollowUpCallNota(string $sourceCallId, string $dataRichiamo): string
    {
        return self::NOTA_RICHIAMO_CALL_PREFIX . ' ' . $sourceCallId
            . "\nRichiamo programmato dopo esito contatto per "
            . BusinessDateTime::formatBusiness($dataRichiamo, 'd/m/Y H:i');
    }

    private function findExistingCallId(string $appuntamentoId): ?string
    {
        $existing = $this->entityManager
            ->getRDBRepository('Call')
            ->where([
                'nota*' => self::NOTA_PREFIX . ' ' . $appuntamentoId,
            ])
            ->findOne();

        return $existing?->getId();
    }

    public function syncPopupReminders(Entity $call): void
    {
        if ($call->getEntityType() !== 'Call' || $call->get('status') !== 'Planned') {
            return;
        }

        $callId = $call->getId();
        $dateStart = $call->get('dateStart');

        if (!$callId || !$dateStart) {
            return;
        }

        $userIds = $this->resolveCallReminderUserIds($call);

        if ($userIds === []) {
            return;
        }

        $remindAt = $this->buildRemindAtUtc((string) $dateStart, self::REMINDER_SECONDS);
        $existing = $this->loadPopupReminderMap($callId);

        foreach ($userIds as $userId) {
            $current = $existing[$userId] ?? null;

            if (
                $current
                && $current['remindAt'] === $remindAt
                && (int) $current['seconds'] === self::REMINDER_SECONDS
            ) {
                continue;
            }

            if ($current && $current['id']) {
                $reminder = $this->entityManager->getEntityById('Reminder', $current['id']);

                if ($reminder) {
                    $this->entityManager->removeEntity($reminder, ['skipAcl' => true]);
                }
            }

            $this->entityManager->createEntity('Reminder', [
                'entityType' => 'Call',
                'entityId' => $callId,
                'type' => Reminder::TYPE_POPUP,
                'userId' => $userId,
                'seconds' => self::REMINDER_SECONDS,
                'remindAt' => $remindAt,
                'startAt' => $dateStart,
            ], [
                'skipAcl' => true,
                'silent' => true,
                'skipHooks' => true,
            ]);
        }

        foreach ($existing as $userId => $data) {
            if (in_array($userId, $userIds, true) || !$data['id']) {
                continue;
            }

            $reminder = $this->entityManager->getEntityById('Reminder', $data['id']);

            if ($reminder) {
                $this->entityManager->removeEntity($reminder, ['skipAcl' => true]);
            }
        }
    }

    /**
     * @return string[]
     */
    private function resolveCallReminderUserIds(Entity $call): array
    {
        $userIds = array_values(array_filter(array_map(
            'strval',
            $call->get('usersIds') ?: []
        )));

        if ($userIds !== []) {
            return array_values(array_unique($userIds));
        }

        $assignedUserId = $call->get('assignedUserId');

        return $assignedUserId ? [(string) $assignedUserId] : [];
    }

    /**
     * @return array<string, array{id: ?string, remindAt: ?string, seconds: int}>
     */
    private function loadPopupReminderMap(string $callId): array
    {
        $map = [];

        $collection = $this->entityManager
            ->getRDBRepository('Reminder')
            ->select(['id', 'userId', 'remindAt', 'seconds'])
            ->where([
                'entityType' => 'Call',
                'entityId' => $callId,
                'type' => Reminder::TYPE_POPUP,
            ])
            ->find();

        foreach ($collection as $reminder) {
            $userId = (string) $reminder->get('userId');

            if ($userId === '') {
                continue;
            }

            $map[$userId] = [
                'id' => $reminder->getId(),
                'remindAt' => $reminder->get('remindAt'),
                'seconds' => (int) $reminder->get('seconds'),
            ];
        }

        return $map;
    }

    private function buildRemindAtUtc(string $dateStartUtc, int $secondsBefore): string
    {
        try {
            $start = new \DateTimeImmutable($dateStartUtc, new \DateTimeZone('UTC'));
        } catch (\Throwable) {
            $start = new \DateTimeImmutable($dateStartUtc);
        }

        if ($secondsBefore <= 0) {
            return $start->format('Y-m-d H:i:s');
        }

        return $start->modify('-' . $secondsBefore . ' seconds')->format('Y-m-d H:i:s');
    }
}
