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

    public function createIfNeeded(Entity $appuntamento): void
    {
        if ($appuntamento->get('status') !== 'Held') {
            return;
        }

        if ($appuntamento->get('sottostato') !== 'Pending') {
            return;
        }

        if (!$this->shouldCreate($appuntamento)) {
            return;
        }

        $appuntamentoId = $appuntamento->getId();

        if (!$appuntamentoId) {
            return;
        }

        if ($this->findExistingCall($appuntamentoId)) {
            return;
        }

        $leadId = $appuntamento->get('parentId');
        $leadType = $appuntamento->get('parentType');

        if ($leadType !== 'Lead' || !$leadId) {
            return;
        }

        $lead = $this->entityManager->getEntityById('Lead', $leadId);

        if (!$lead) {
            return;
        }

        $callDateStart = PendingCallDateTime::fromAppointmentDateStart(
            $appuntamento->get('dateStart')
        );

        if (!$callDateStart) {
            return;
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
    }

    private function shouldCreate(Entity $appuntamento): bool
    {
        if (
            $appuntamento->isAttributeChanged('sottostato') &&
            $appuntamento->get('sottostato') === 'Pending'
        ) {
            return true;
        }

        return $appuntamento->isAttributeChanged('status') &&
            $appuntamento->get('status') === 'Held' &&
            $appuntamento->get('sottostato') === 'Pending';
    }

    private function findExistingCall(string $appuntamentoId): bool
    {
        $existing = $this->entityManager
            ->getRDBRepository('Call')
            ->where([
                'nota' => self::NOTA_PREFIX . ' ' . $appuntamentoId,
            ])
            ->findOne();

        return (bool) $existing;
    }
}
