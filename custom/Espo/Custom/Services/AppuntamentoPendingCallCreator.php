<?php

namespace Espo\Custom\Services;

use Espo\Core\Utils\Log;
use Espo\Custom\Tools\Appuntamento\PendingCallDateTime;
use Espo\Custom\Tools\DateTime\BusinessDateTime;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class AppuntamentoPendingCallCreator
{
    private const NOTA_PREFIX = 'Auto-Pending-Appuntamento:';
    private const TIPOLOGIA = 'Richiamo su Opportunità Generata';
    private const TESTO_STANDARD =
        'Salve, sono Carmine Alvino di ARIEL ENERGIA, mi fa sapere entro la giornata di oggi '
        . 'poi cosa ha deciso rispetto alla proposta che le ho fatto, Grazie';
    private const REMINDER_SECONDS = 900;

    /** @var array<string, string> */
    private static array $rememberedLeadIds = [];

    public function __construct(
        private EntityManager $entityManager,
        private Log $log
    ) {}

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

        if ($this->findExistingCallId($appuntamentoId)) {
            return null;
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
            'testo' => self::TESTO_STANDARD,
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

    public function resolveOwnerUserId(Entity $appuntamento): ?string
    {
        $assignedUsersIds = $appuntamento->get('assignedUsersIds');

        if (is_array($assignedUsersIds) && $assignedUsersIds !== []) {
            return (string) $assignedUsersIds[0];
        }

        $assignedUserId = $appuntamento->get('assignedUserId');

        if ($assignedUserId) {
            return (string) $assignedUserId;
        }

        $createdById = $appuntamento->get('createdById');

        return $createdById ? (string) $createdById : null;
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
     * @return array{name: string, data: string, whatsAppNumero: ?string, dateEnd: null}
     */
    public function buildCallPresentationFields(
        \DateTimeImmutable $callInstant,
        ?string $parentName,
        ?string $telefono
    ): array {
        $dataLabel = PendingCallDateTime::formatBusinessDateTime($callInstant);
        $localDate = PendingCallDateTime::formatBusinessDateTime($callInstant, 'Y-m-d');

        $parentName = trim((string) $parentName);
        $telefono = trim((string) $telefono);

        $name = mb_strtoupper(
            $dataLabel . ' - ' . self::TIPOLOGIA . ' - ' . $parentName . ' - ' . $telefono,
            'UTF-8'
        );

        $whatsAppNumero = null;

        if ($telefono !== '') {
            $digits = preg_replace('/\D+/', '', $telefono) ?: '';
            $whatsAppNumero = $digits !== '' ? 'https://wa.me/+39' . $digits : null;
        }

        return [
            'name' => $name,
            'data' => $localDate,
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
}
