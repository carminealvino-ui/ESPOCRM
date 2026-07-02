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
    public const CREATOR_VERSION = '2026-07-01b';

    private ?string $lastFailureReason = null;

    public function getLastFailureReason(): ?string
    {
        return $this->lastFailureReason;
    }

    private function fail(string $reason): null
    {
        $this->lastFailureReason = $reason;

        return null;
    }

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
        $this->lastFailureReason = null;

        if ($appuntamento->get('status') !== 'Held') {
            return $this->fail('status=' . (string) $appuntamento->get('status') . ' (atteso Held)');
        }

        if ($appuntamento->get('sottostato') !== 'Pending') {
            return $this->fail('sottostato=' . (string) $appuntamento->get('sottostato') . ' (atteso Pending)');
        }

        if (!PendingCallDateTime::isAppointmentEligible($appuntamento->get('dateStart'))) {
            return $this->fail('appuntamento prima del 2026');
        }

        $appuntamentoId = $appuntamento->getId();

        if (!$appuntamentoId) {
            return $this->fail('appuntamento senza id');
        }

        $existingCallId = $this->findExistingPlannedPendingCallId($appuntamentoId);

        if ($existingCallId) {
            $this->syncCallOwnerFromAppuntamento($existingCallId, $appuntamento);
            $existingCall = $this->entityManager->getEntityById('Call', $existingCallId);

            if ($existingCall) {
                $this->syncPopupRemindersSafe($existingCall);
            }

            return $existingCallId;
        }

        $prospect = $this->resolveProspect($appuntamento);
        $leadId = $leadIdOverride ?: $this->resolveLeadId($appuntamento);

        if (!$leadId && $prospect) {
            try {
                $leadId = $this->ensureLeadId($appuntamento);
            } catch (\Throwable $e) {
                $this->log->warning(
                    'Auto-create Call Pending: ensureLeadId fallito per Appuntamento {id}, uso Prospect: {message}',
                    [
                        'id' => $appuntamentoId,
                        'message' => $e->getMessage(),
                        'exception' => $e,
                    ]
                );
                $leadId = null;
            }
        }

        $parentType = 'Lead';
        $parentId = $leadId;
        $parentEntity = $leadId ? $this->entityManager->getEntityById('Lead', $leadId) : null;

        if (!$parentEntity && $prospect) {
            $parentType = 'Prospect';
            $parentId = $prospect->getId();
            $parentEntity = $prospect;
        }

        if (!$parentEntity || !$parentId) {
            $this->log->warning(
                'Auto-create Call Pending: Lead/Prospect non trovato per Appuntamento {id} (parentType={parentType}, parentId={parentId}, prospectId={prospectId})',
                [
                    'id' => $appuntamentoId,
                    'parentType' => (string) $appuntamento->get('parentType'),
                    'parentId' => (string) $appuntamento->get('parentId'),
                    'prospectId' => (string) $appuntamento->get('prospectId'),
                ]
            );

            return $this->fail('lead/prospect non risolvibile (parent='
                . (string) $appuntamento->get('parentType')
                . '/' . (string) $appuntamento->get('parentId')
                . ' prospect=' . (string) $appuntamento->get('prospectId') . ')');
        }

        unset(self::$rememberedLeadIds[$appuntamentoId]);

        $notBefore = $this->resolveNotBeforeForCall($appuntamento, $notBefore);
        $callInstant = $this->buildCallInstantFromAppointment($appuntamento, $notBefore);

        if (!$callInstant) {
            return $this->fail('data richiamo non calcolabile');
        }

        $callDateStartUtc = BusinessDateTime::businessToStorage($callInstant);
        $leadSync = new LeadProspectSync($this->entityManager);
        $parentName = trim((string) ($appuntamento->get('parentName') ?: $leadSync->resolveDisplayName($parentEntity)));
        $telefono = trim((string) ($appuntamento->get('telefono')
            ?: ($parentType === 'Lead'
                ? $parentEntity->get('phoneNumber')
                : $leadSync->resolvePhoneFromProspect($parentEntity))));

        if ($telefono === '') {
            return $this->fail('telefono mancante su appuntamento/lead/prospect');
        }

        $presentation = $this->buildCallPresentationFields(
            BusinessDateTime::storageToBusiness($appuntamento->get('dateStart')),
            $parentName,
            $telefono
        );
        $ownerUserId = $this->resolveOwnerUserId($appuntamento) ?: self::ADMIN_USER_ID;
        $ownerUserName = $this->resolveOwnerUserName($ownerUserId);
        $prospectId = $prospect?->getId() ?: $appuntamento->get('prospectId');

        if (!$prospectId && $parentType === 'Lead') {
            $prospectId = $parentEntity->get('prospectId')
                ?: (new LeadProspectSync($this->entityManager))->findProspectForLead($parentEntity)?->getId();
        }

        $prospectName = $prospect?->get('name') ?: $appuntamento->get('prospectName');

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
            'parentType' => $parentType,
            'parentId' => $parentId,
            'parentName' => $parentName,
            'prospectId' => $prospectId,
            'prospectName' => $prospectName,
            'telefono' => $telefono,
            'dateStart' => $callDateStartUtc,
            'assignedUserId' => $ownerUserId,
            'assignedUserName' => $ownerUserName,
            'usersIds' => [$ownerUserId],
            'usersNames' => $usersNames,
            'daRichiamare' => false,
            'whatsApp' => true,
            'vocale' => false,
            'testo' => $this->getStandardTesto(),
            'nota' => $this->buildNota($appuntamentoId, $appuntamento->get('dateStart')),
        ]));

        // skipHooks: bypass formula Call; dateStart già in UTC. Promemoria creati a mano sotto.
        try {
            $this->entityManager->saveEntity($call, [
                'skipAcl' => true,
                'silent' => true,
                'skipHooks' => true,
                'isImport' => true,
            ]);
        } catch (\Throwable $e) {
            $message = $e->getMessage();

            $this->log->error(
                'Auto-create Call Pending: salvataggio fallito per Appuntamento {id}: {message}',
                [
                    'id' => $appuntamentoId,
                    'message' => $message,
                    'exception' => $e,
                ]
            );

            return $this->fail('salvataggio Call fallito: ' . $message);
        }

        $callId = $call->getId();

        if (!$callId) {
            return $this->fail('salvataggio Call senza id');
        }

        $this->syncPopupRemindersSafe($call);

        $this->log->info(
            'Auto-create Call Pending: creata Call {callId} per Appuntamento {id} UTC {dateStartUtc} (Rome {dateStartRome})',
            [
                'callId' => $callId,
                'id' => $appuntamentoId,
                'dateStartUtc' => $callDateStartUtc,
                'dateStartRome' => PendingCallDateTime::formatBusinessDateTime($callInstant),
            ]
        );

        return $callId;
    }

    public function diagnoseCreateBlockReason(Entity $appuntamento): ?string
    {
        if ($appuntamento->get('status') !== 'Held') {
            return 'status=' . (string) $appuntamento->get('status') . ' (atteso Held)';
        }

        if ($appuntamento->get('sottostato') !== 'Pending') {
            return 'sottostato=' . (string) $appuntamento->get('sottostato') . ' (atteso Pending)';
        }

        if (!PendingCallDateTime::isAppointmentEligible($appuntamento->get('dateStart'))) {
            return 'appuntamento prima del 2026';
        }

        $appuntamentoId = $appuntamento->getId();

        if (!$appuntamentoId) {
            return 'appuntamento senza id';
        }

        if ($this->findExistingPlannedPendingCallId($appuntamentoId)) {
            return 'call pianificata già presente';
        }

        $prospect = $this->resolveProspect($appuntamento);
        $leadId = $this->resolveLeadId($appuntamento);
        $hasParent = ($leadId && $this->entityManager->getEntityById('Lead', $leadId)) || $prospect;

        if (!$hasParent) {
            try {
                $leadId = $this->ensureLeadId($appuntamento);
                $hasParent = (bool) ($leadId && $this->entityManager->getEntityById('Lead', $leadId));
            } catch (\Throwable) {
                $hasParent = $prospect !== null;
            }
        }

        if (!$hasParent) {
            return 'lead/prospect non risolvibile (parent='
                . (string) $appuntamento->get('parentType')
                . '/' . (string) $appuntamento->get('parentId')
                . ' prospect=' . (string) $appuntamento->get('prospectId') . ')';
        }

        if (!$this->buildEffectiveCallInstant($appuntamento)) {
            return 'data richiamo non calcolabile';
        }

        $leadSync = new LeadProspectSync($this->entityManager);
        $telefono = trim((string) $appuntamento->get('telefono'));

        if ($telefono === '' && $leadId) {
            $lead = $this->entityManager->getEntityById('Lead', $leadId);
            $telefono = trim((string) ($lead?->get('phoneNumber') ?: ''));
        }

        if ($telefono === '' && $prospect) {
            $telefono = trim((string) ($leadSync->resolvePhoneFromProspect($prospect) ?: ''));
        }

        if ($telefono === '') {
            return 'telefono mancante su appuntamento/lead/prospect';
        }

        return null;
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

        $existingCallId = $this->findExistingRichiamoCallId($appuntamentoId, $dataRichiamo);

        if ($existingCallId) {
            $this->syncCallOwnerFromAppuntamento($existingCallId, $appuntamento);
            $existingCall = $this->entityManager->getEntityById('Call', $existingCallId);

            if ($existingCall) {
                $this->syncPopupReminders($existingCall);
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
        $appointmentInstant = $appuntamento->get('dateStart') ?
            BusinessDateTime::storageToBusiness($appuntamento->get('dateStart')) :
            $callInstant;
        $presentation = $this->buildCallPresentationFields(
            $appointmentInstant,
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

    public function buildEffectiveCallInstant(Entity $appuntamento): ?\DateTimeImmutable
    {
        $notBefore = $this->resolveNotBeforeForCall($appuntamento, null);

        return $this->buildCallInstantFromAppointment($appuntamento, $notBefore);
    }

    /**
     * Campi presentazione Call: il nome usa la data appuntamento (non dateStart della Call).
     *
     * @return array{name: string, whatsAppNumero: ?string, dateEnd: null}
     */
    public function buildCallPresentationFields(
        ?\DateTimeImmutable $appointmentInstant,
        ?string $parentName,
        ?string $telefono,
        ?string $tipologia = null
    ): array {
        if (!$appointmentInstant) {
            $appointmentInstant = new \DateTimeImmutable(
                'now',
                new \DateTimeZone(BusinessDateTime::BUSINESS_TIMEZONE)
            );
        }

        $dataLabel = PendingCallDateTime::formatBusinessDateTime($appointmentInstant);
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

    public function extractAppuntamentoIdFromNota(string $nota): ?string
    {
        if (preg_match('/Auto-(?:Pending|Richiamo)-Appuntamento:\s*([a-z0-9]{17})/i', $nota, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function syncCallNameFromAppuntamento(Entity $call, Entity $appuntamento): bool
    {
        $dateStart = $appuntamento->get('dateStart');

        if (!$dateStart) {
            return false;
        }

        $presentation = $this->buildCallPresentationFields(
            BusinessDateTime::storageToBusiness($dateStart),
            $call->get('parentName'),
            $call->get('telefono'),
            $call->get('tipologia')
        );

        $newName = $presentation['name'];

        if ((string) $call->get('name') === $newName) {
            return false;
        }

        $call->set('name', $newName);

        return true;
    }

    public function applyRinvioDefaultsToEntity(Entity $call): void
    {
        if (!$call->get('daRichiamare')) {
            return;
        }

        if (!$call->get('dataRichiamo')) {
            $call->set('dataRichiamo', PendingCallDateTime::defaultRinvioDateTimeUtc());
        }

        if (!trim((string) $call->get('richiamo'))) {
            $call->set('richiamo', $call->get('tipologia'));
        }
    }

    /**
     * Rinvio richiamo ancora Pianificato: sposta dateStart (stessa Call, nome invariato).
     */
    public function applyRinvioToEntity(Entity $call): bool
    {
        if ($call->getEntityType() !== 'Call' || (string) $call->get('status') !== 'Planned') {
            return false;
        }

        if (!$call->get('daRichiamare')) {
            return false;
        }

        $this->applyRinvioDefaultsToEntity($call);

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
        $nota = trim((string) $call->get('nota'));
        $rinvioLine = $this->buildRinvioNotaLine($previousDateStart, $dataRichiamo);

        $call->set([
            'dateStart' => BusinessDateTime::businessToStorage($callInstant),
            'tipologia' => $tipologia,
            'richiamo' => $tipologia,
            'daRichiamare' => false,
            'dataRichiamo' => null,
            'nota' => $nota !== '' ? $nota . "\n" . $rinvioLine : $rinvioLine,
        ]);

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

        $this->applyRinvioDefaultsToEntity($sourceCall);

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
                $this->applyRinvioToStoredCall($existingCall, (string) $dataRichiamo, $tipologia);
            } elseif ($existingCall) {
                $this->syncPopupReminders($existingCall);
            }

            return $existingCallId;
        }

        $callInstant = BusinessDateTime::storageToBusiness((string) $dataRichiamo);
        $callDateStartUtc = BusinessDateTime::businessToStorage($callInstant);
        $parentName = $sourceCall->get('parentName');
        $telefono = $sourceCall->get('telefono');
        $name = $this->buildFollowUpCallName($sourceCall, $tipologia);

        $call = $this->entityManager->createEntity('Call');

        $ownerUserId = $sourceCall->get('assignedUserId');
        $ownerUserName = $sourceCall->get('assignedUserName');
        $usersNames = [];

        if ($ownerUserId && $ownerUserName) {
            $usersNames[(string) $ownerUserId] = (string) $ownerUserName;
        }

        $digits = preg_replace('/\D+/', '', (string) $telefono) ?: '';
        $whatsAppNumero = $digits !== '' ? 'https://wa.me/+39' . $digits : $sourceCall->get('whatsAppNumero');

        $call->set([
            'name' => $name,
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
            'whatsAppNumero' => $whatsAppNumero,
            'dateStart' => $callDateStartUtc,
            'dateEnd' => null,
            'assignedUserId' => $ownerUserId,
            'assignedUserName' => $ownerUserName,
            'usersIds' => $ownerUserId ? [(string) $ownerUserId] : [],
            'usersNames' => $usersNames,
            'daRichiamare' => false,
            'whatsApp' => (bool) $sourceCall->get('whatsApp'),
            'vocale' => (bool) $sourceCall->get('vocale'),
            'testo' => $sourceCall->get('testo') ?: $this->getStandardTesto(),
            'nota' => $this->buildFollowUpCallNota($sourceCallId, (string) $dataRichiamo),
        ]);

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

    private function findPlannedFollowUpCallId(string $sourceCallId): ?string
    {
        foreach ([self::NOTA_RICHIAMO_CALL_PREFIX, 'Auto-Rinvio-Call:'] as $prefix) {
            $existing = $this->entityManager
                ->getRDBRepository('Call')
                ->where([
                    'nota*' => $prefix . ' ' . $sourceCallId,
                    'status' => 'Planned',
                ])
                ->findOne();

            if ($existing) {
                return $existing->getId();
            }
        }

        return null;
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

    private function buildFollowUpCallName(Entity $sourceCall, string $tipologia): string
    {
        $name = (string) $sourceCall->get('name');

        if ($name !== '' && $tipologia !== '') {
            $parts = explode(' - ', $name);

            if (count($parts) >= 4) {
                $parts[1] = $tipologia;

                return mb_strtoupper(implode(' - ', $parts), 'UTF-8');
            }
        }

        return $name !== '' ? $name : ('RICHIAMO - ' . $sourceCall->getId());
    }

    public function resolveProspect(Entity $appuntamento): ?Entity
    {
        if ($appuntamento->get('parentType') === 'Prospect') {
            $parentId = $appuntamento->get('parentId');

            if ($parentId) {
                $prospect = $this->entityManager->getEntityById('Prospect', $parentId);

                if ($prospect) {
                    return $prospect;
                }
            }
        }

        $prospectId = $appuntamento->get('prospectId');

        if ($prospectId) {
            $prospect = $this->entityManager->getEntityById('Prospect', $prospectId);

            if ($prospect) {
                return $prospect;
            }
        }

        $telefono = trim((string) $appuntamento->get('telefono'));

        if ($telefono === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $telefono) ?: '';

        if ($digits === '') {
            return null;
        }

        $repository = $this->entityManager->getRDBRepository('Prospect');

        foreach (['phoneNumber', 'telefono'] as $field) {
            $prospect = $repository->where([$field => $digits])->findOne();

            if ($prospect) {
                return $prospect;
            }

            if (str_starts_with($digits, '39') && strlen($digits) > 10) {
                $local = substr($digits, 2);
                $prospect = $repository->where([$field => $local])->findOne();

                if ($prospect) {
                    return $prospect;
                }
            }
        }

        return null;
    }

    public function resolveLeadId(Entity $appuntamento): ?string
    {
        $appuntamentoId = $appuntamento->getId();

        if ($appuntamentoId && isset(self::$rememberedLeadIds[$appuntamentoId])) {
            $leadId = self::$rememberedLeadIds[$appuntamentoId];

            if ($this->entityManager->getEntityById('Lead', $leadId)) {
                return $leadId;
            }
        }

        if ($appuntamento->get('parentType') === 'Lead') {
            $parentId = $appuntamento->get('parentId');

            if ($parentId && $this->entityManager->getEntityById('Lead', $parentId)) {
                return $parentId;
            }
        }

        $prospect = $this->resolveProspect($appuntamento);

        if (!$prospect) {
            return null;
        }

        $lead = (new LeadProspectSync($this->entityManager))
            ->findExistingLeadByProspect($prospect);

        return $lead?->getId();
    }

    /**
     * Risolve il Lead collegato; se manca ma c'è il Prospect, lo crea come GlobalLogic.
     */
    public function ensureLeadId(Entity $appuntamento): ?string
    {
        $leadId = $this->resolveLeadId($appuntamento);

        if ($leadId) {
            $lead = $this->entityManager->getEntityById('Lead', $leadId);

            if ($lead) {
                return $leadId;
            }
        }

        $prospect = $this->resolveProspect($appuntamento);

        if (!$prospect) {
            return null;
        }

        $leadSync = new LeadProspectSync($this->entityManager);
        $lead = $leadSync->findExistingLeadByProspect($prospect);
        $isNewLead = !$lead;

        if (!$lead) {
            $lead = $this->entityManager->createEntity('Lead');

            $leadSync->syncLeadFromProspect($lead, $prospect, false);
            $lead->set(['status' => 'In Process']);

            $ownerUserId = $this->resolveOwnerUserId($appuntamento);

            if ($ownerUserId) {
                $lead->set('assignedUserId', $ownerUserId);
            }

            if (!trim((string) $lead->get('name'))) {
                $lead->set('name', $leadSync->resolveDisplayName($prospect) ?: 'Prospect ' . $prospectId);
            }

            $this->entityManager->saveEntity($lead, [
                'skipAcl' => true,
                'silent' => true,
            ]);
        }

        $leadSync->syncLeadFromProspect($lead, $prospect, !$isNewLead);
        $lead->set('status', 'In Process');
        $leadSync->linkLeadAndProspect($lead, $prospect);

        $this->entityManager->saveEntity($lead, [
            'skipAcl' => true,
            'silent' => true,
        ]);

        $leadId = $lead->getId();

        if (!$leadId) {
            return null;
        }

        $appuntamentoId = $appuntamento->getId();

        if ($appuntamentoId) {
            $leadName = $lead->get('name');

            if (!$leadName) {
                $leadName = trim(
                    ($lead->get('firstName') ?: '') . ' ' . ($lead->get('lastName') ?: '')
                );
            }

            $fresh = $this->entityManager->getEntityById('Appuntamento', $appuntamentoId);

            if ($fresh) {
                $fresh->set([
                    'parentType' => 'Lead',
                    'parentId' => $leadId,
                    'parentName' => $leadName ?: $fresh->get('parentName'),
                ]);

                $this->entityManager->saveEntity($fresh, [
                    'skipAcl' => true,
                    'silent' => true,
                    'skipHooks' => true,
                    'skipAutoCreatePendingCall' => true,
                ]);

                $appuntamento->set([
                    'parentType' => 'Lead',
                    'parentId' => $leadId,
                    'parentName' => $leadName ?: $appuntamento->get('parentName'),
                ]);
            }
        }

        return $leadId;
    }

    /**
     * Se la data richiamo calcolata è nel passato, slitta a oggi (o lunedì se weekend).
     */
    private function resolveNotBeforeForCall(
        Entity $appuntamento,
        ?\DateTimeImmutable $notBefore
    ): ?\DateTimeImmutable {
        if ($notBefore !== null) {
            return $notBefore;
        }

        $dateStart = $appuntamento->get('dateStart');

        if (!$dateStart) {
            return null;
        }

        $timezone = new \DateTimeZone(BusinessDateTime::BUSINESS_TIMEZONE);
        $scheduled = PendingCallDateTime::computeCallInstant(
            BusinessDateTime::storageToBusiness($dateStart),
            null
        );
        $now = new \DateTimeImmutable('now', $timezone);

        if ($scheduled >= $now) {
            return null;
        }

        return new \DateTimeImmutable('today', $timezone);
    }

    private function findExistingPlannedPendingCallId(string $appuntamentoId): ?string
    {
        $existing = $this->entityManager
            ->getRDBRepository('Call')
            ->where([
                'nota*' => self::NOTA_PREFIX . ' ' . $appuntamentoId,
                'status' => 'Planned',
            ])
            ->findOne();

        return $existing?->getId();
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

    private function findExistingRichiamoCallId(string $appuntamentoId, string $dataRichiamo): ?string
    {
        $collection = $this->entityManager
            ->getRDBRepository('Call')
            ->where([
                'nota*' => self::NOTA_RICHIAMO_PREFIX . ' ' . $appuntamentoId,
                'status' => 'Planned',
            ])
            ->find();

        foreach ($collection as $call) {
            if ($call->get('dateStart') === $dataRichiamo) {
                return $call->getId();
            }
        }

        return null;
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
            $userIds = [self::ADMIN_USER_ID];
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

    private function syncPopupRemindersSafe(Entity $call): void
    {
        try {
            $this->syncPopupReminders($call);
        } catch (\Throwable $e) {
            $this->log->warning(
                'Auto-create Call Pending: promemoria non creati per Call {callId}: {message}',
                [
                    'callId' => $call->getId(),
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]
            );
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
