<?php

namespace Espo\Custom\Services;

use Espo\Core\Utils\Config;
use Espo\Core\Utils\Log;
use Espo\Custom\Tools\Appuntamento\PendingCallDateTime;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class AppuntamentoPendingCallCreator
{
    private const NOTA_PREFIX = 'Auto-Pending-Appuntamento:';
    private const TIPOLOGIA = 'Contatto dopo Prima Visita';
    private const REMINDER_SECONDS = 900;

    /** @var array<string, string> */
    private static array $rememberedLeadIds = [];

    public function __construct(
        private EntityManager $entityManager,
        private Log $log,
        private ?Config $config = null
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

        $applicationTimeZone = $this->getApplicationTimeZone();
        $callInstant = $this->buildCallInstantFromAppointment($appuntamento, $notBefore);

        if (!$callInstant) {
            return null;
        }

        $callDateStart = PendingCallDateTime::formatForApplicationTimezone($callInstant, $applicationTimeZone);

        $parentName = $appuntamento->get('parentName') ?: $lead->get('name');
        $telefono = $appuntamento->get('telefono') ?: $lead->get('phoneNumber');
        $presentation = $this->buildCallPresentationFields($callInstant, $parentName, $telefono);
        $ownerUserId = $this->resolveOwnerUserId($appuntamento);

        $call = $this->entityManager->createEntity('Call');

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
            'dateStart' => $callDateStart,
            'assignedUserId' => $ownerUserId,
            'daRichiamare' => false,
            'whatsApp' => false,
            'vocale' => true,
            'nota' => $this->buildNota($appuntamentoId, $appuntamento->get('dateStart')),
            'reminders' => [
                [
                    'type' => 'popup',
                    'seconds' => self::REMINDER_SECONDS,
                ],
            ],
        ]));

        // Niente skipHooks: il repository Event converte dateStart (tz app → UTC).
        // La formula Call salta grazie al marker in nota.
        $this->entityManager->saveEntity($call, [
            'skipAcl' => true,
            'silent' => true,
        ]);

        $this->log->info(
            'Auto-create Call Pending: creata Call {callId} per Appuntamento {id} alle {dateStart} ({timeZone})',
            [
                'callId' => $call->getId(),
                'id' => $appuntamentoId,
                'dateStart' => $callDateStart,
                'timeZone' => $applicationTimeZone,
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

    public function buildExpectedCallDateStart(
        Entity $appuntamento,
        ?\DateTimeImmutable $notBefore = null
    ): ?string {
        $instant = $this->buildCallInstantFromAppointment($appuntamento, $notBefore);

        if (!$instant) {
            return null;
        }

        return PendingCallDateTime::formatForApplicationTimezone(
            $instant,
            $this->getApplicationTimeZone()
        );
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
            $this->parseAppointmentStoredDateTime($dateStart),
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

    private function getApplicationTimeZone(): string
    {
        $timeZone = $this->config?->get('timeZone');

        if (is_string($timeZone) && $timeZone !== '') {
            return $timeZone;
        }

        return PendingCallDateTime::BUSINESS_TIMEZONE;
    }

    private function parseAppointmentStoredDateTime(?string $dateStart): \DateTimeImmutable
    {
        $utc = new \DateTimeZone('UTC');

        if (!$dateStart) {
            return new \DateTimeImmutable('now', $utc);
        }

        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateStart, $utc);

        if ($parsed) {
            return $parsed;
        }

        return new \DateTimeImmutable($dateStart, $utc);
    }

    private function buildNota(string $appuntamentoId, ?string $appointmentDateStart): string
    {
        $lines = [
            self::NOTA_PREFIX . ' ' . $appuntamentoId,
        ];

        if ($appointmentDateStart) {
            $lines[] = 'Richiamo automatico per appuntamento Pending del ' . $appointmentDateStart;
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
