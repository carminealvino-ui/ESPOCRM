<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Risolve e collega Opportunity a Contatto Telefonico (Call).
 */
class CallOpportunityLinker
{
    public function __construct(
        private EntityManager $entityManager,
    ) {}

    public function linkOnCall(Entity $call): bool
    {
        if ($call->getEntityType() !== 'Call') {
            return false;
        }

        if ($call->get('opportunityId')) {
            return false;
        }

        $opportunityId = $this->resolveOpportunityId($call);

        if (!$opportunityId) {
            return false;
        }

        $opportunity = $this->entityManager->getEntityById('Opportunity', $opportunityId);

        if (!$opportunity) {
            return false;
        }

        $call->set('opportunityId', $opportunityId);
        $call->set('opportunityName', $opportunity->get('name'));

        return true;
    }

    public function resolveOpportunityIdForAppuntamento(string $appuntamentoId): ?string
    {
        if ($appuntamentoId === '') {
            return null;
        }

        $opportunity = $this->entityManager
            ->getRDBRepository('Opportunity')
            ->where(['appuntamentoId' => $appuntamentoId])
            ->order('createdAt', 'DESC')
            ->findOne();

        return $opportunity ? $opportunity->getId() : null;
    }

    public function resolveOpportunityId(Entity $call): ?string
    {
        if ($call->get('parentType') === 'Opportunity' && $call->get('parentId')) {
            return (string) $call->get('parentId');
        }

        if ($call->get('opportunityId')) {
            return (string) $call->get('opportunityId');
        }

        $appuntamentoId = $this->extractAppuntamentoId((string) $call->get('nota'))
            ?: $this->extractAppuntamentoId((string) $call->get('description'));

        if ($appuntamentoId) {
            $fromApp = $this->resolveOpportunityIdForAppuntamento($appuntamentoId);

            if ($fromApp) {
                return $fromApp;
            }
        }

        $prospectId = $call->get('prospectId');

        if (!$prospectId && $call->get('parentType') === 'Prospect' && $call->get('parentId')) {
            $prospectId = $call->get('parentId');
        }

        if ($prospectId) {
            $opportunity = $this->entityManager
                ->getRDBRepository('Opportunity')
                ->where(['prospectId' => $prospectId])
                ->order('createdAt', 'DESC')
                ->findOne();

            if ($opportunity) {
                return $opportunity->getId();
            }
        }

        if ($call->get('parentType') === 'Lead' && $call->get('parentId')) {
            $opportunity = $this->entityManager
                ->getRDBRepository('Opportunity')
                ->where(['leadId' => $call->get('parentId')])
                ->order('createdAt', 'DESC')
                ->findOne();

            if ($opportunity) {
                return $opportunity->getId();
            }
        }

        $phone = $this->normalizePhone((string) $call->get('telefono'));

        if ($phone === '') {
            $phone = $this->normalizePhone($this->extractPhoneFromCallName((string) $call->get('name')));
        }

        if ($phone !== '') {
            $collection = $this->entityManager
                ->getRDBRepository('Opportunity')
                ->select(['id', 'telefono'])
                ->order('createdAt', 'DESC')
                ->limit(0, 500)
                ->find();

            foreach ($collection as $opportunity) {
                if ($this->normalizePhone((string) $opportunity->get('telefono')) === $phone) {
                    return $opportunity->getId();
                }
            }
        }

        return null;
    }

    public function extractAppuntamentoId(string $nota): ?string
    {
        if (preg_match('/Auto-(?:Pending|Richiamo)-Appuntamento:\s*([a-z0-9]{17})/i', $nota, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Nome tipico: "22/07/2026 19:30 - RICHIAMO ... - COGNOME NOME - 3894931312"
     */
    public function extractPhoneFromCallName(string $name): string
    {
        if (preg_match('/(\d{8,15})\s*$/', trim($name), $matches)) {
            return $matches[1];
        }

        return '';
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '39') && strlen($digits) > 10) {
            $digits = substr($digits, 2);
        }

        return $digits;
    }
}
