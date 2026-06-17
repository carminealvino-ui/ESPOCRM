<?php

namespace Espo\Custom\Services;

use Espo\Core\Utils\Log;
use Espo\Custom\Tools\Appuntamento\PendingCallDateTime;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class AppuntamentoPendingCallCreator
{
    private const NOTA_PREFIX = 'Auto-Pending-Appuntamento:';
    private const TIPOLOGIA = 'Contatto dopo Prima Visita';
    private const CALL_CENTER_USER_ID = '1';
    private const REMINDER_SECONDS = 900;

    public function __construct(
        private EntityManager $entityManager,
        private Log $log
    ) {}

    public function createIfNeeded(Entity $appuntamento): ?string
    {
        if ($appuntamento->get('status') !== 'Held') {
            return null;
        }

        if ($appuntamento->get('sottostato') !== 'Pending') {
            return null;
        }

        $appuntamentoId = $appuntamento->getId();

        if (!$appuntamentoId) {
            return null;
        }

        if ($this->findExistingCallId($appuntamentoId)) {
            return null;
        }

        $appuntamento = $this->reloadAppuntamento($appuntamentoId) ?? $appuntamento;

        $leadId = $this->resolveLeadId($appuntamento);

        if (!$leadId) {
            $this->log->warning(
                'Auto-create Call Pending: Lead non trovato per Appuntamento {id} (parentType={parentType}, prospectId={prospectId})',
                [
                    'id' => $appuntamentoId,
                    'parentType' => (string) $appuntamento->get('parentType'),
                    'prospectId' => (string) $appuntamento->get('prospectId'),
                ]
            );

            return null;
        }

        $lead = $this->entityManager->getEntityById('Lead', $leadId);

        if (!$lead) {
            return null;
        }

        $callDateStart = PendingCallDateTime::fromAppointmentDateStart(
            $appuntamento->get('dateStart')
        );

        if (!$callDateStart) {
            $this->log->warning(
                'Auto-create Call Pending: dateStart mancante per Appuntamento {id}',
                ['id' => $appuntamentoId]
            );

            return null;
        }

        $parentName = $appuntamento->get('parentName') ?: $lead->get('name');
        $telefono = $appuntamento->get('telefono') ?: $lead->get('phoneNumber');

        $call = $this->entityManager->createEntity('Call');

        $call->set([
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
            'assignedUserId' => self::CALL_CENTER_USER_ID,
            'daRichiamare' => false,
            'whatsApp' => false,
            'vocale' => true,
            'nota' => self::NOTA_PREFIX . ' ' . $appuntamentoId,
            'description' => 'Richiamo automatico per appuntamento Pending del '
                . $appuntamento->get('dateStart'),
            'reminders' => [
                [
                    'type' => 'popup',
                    'seconds' => self::REMINDER_SECONDS,
                ],
            ],
        ]);

        $this->entityManager->saveEntity($call);

        $this->log->info(
            'Auto-create Call Pending: creata Call {callId} per Appuntamento {id} il {dateStart}',
            [
                'callId' => $call->getId(),
                'id' => $appuntamentoId,
                'dateStart' => $callDateStart,
            ]
        );

        return $call->getId();
    }

    private function reloadAppuntamento(string $appuntamentoId): ?Entity
    {
        return $this->entityManager->getEntityById('Appuntamento', $appuntamentoId);
    }

    private function resolveLeadId(Entity $appuntamento): ?string
    {
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
                'nota' => self::NOTA_PREFIX . ' ' . $appuntamentoId,
            ])
            ->findOne();

        return $existing?->getId();
    }
}
