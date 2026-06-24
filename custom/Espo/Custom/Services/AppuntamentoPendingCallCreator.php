<?php

namespace Espo\Custom\Services;

use Espo\Core\Utils\Log;
use Espo\Custom\Services\CallStandardTesto;
use Espo\Custom\Tools\Appuntamento\PendingCallDateTime;
use Espo\Custom\Tools\DateTime\BusinessDateTime;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class AppuntamentoPendingCallCreator
{
    private const NOTA_PREFIX = 'Auto-Pending-Appuntamento:';
    private const NOTA_RICHIAMO_PREFIX = 'Auto-Richiamo-Appuntamento:';
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
            'reminders' => [
                [
                    'type' => 'popup',
                    'seconds' => self::REMINDER_SECONDS,
                ],
            ],
        ]));

        // skipHooks: bypass formula Call; dateStart già in UTC (come Disponibilita/SetName).
        $this->entityManager->saveEntity($call, [
            'skipAcl' => true,
            'silent' => true,
            'skipHooks' => true,
        ]);

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

        $existingCallId = $this->findExistingRichiamoCallId($appuntamentoId, $dataRichiamo);

        if ($existingCallId) {
            $this->syncCallOwnerFromAppuntamento($existingCallId, $appuntamento);

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
            'reminders' => [
                [
                    'type' => 'popup',
                    'seconds' => self::REMINDER_SECONDS,
                ],
            ],
        ]));

        $this->entityManager->saveEntity($call, [
            'skipAcl' => true,
            'silent' => true,
            'skipHooks' => true,
        ]);

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
}
