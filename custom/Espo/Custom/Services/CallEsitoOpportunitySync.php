<?php

namespace Espo\Custom\Services;

use Espo\Custom\Tools\DateTime\BusinessDateTime;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Esito Call "Non interessato" → opportunità Closed Lost + lead Perso.
 *
 * L'Appuntamento Pending resta invariato (storicizzazione): un eventuale
 * interesse in richiamo produce un nuovo appuntamento pianificato.
 */
class CallEsitoOpportunitySync
{
    public const ESITO_NON_INTERESSATO = 'Non interessato';
    private const STAGE_LOST = 'Closed Lost';

    /** @var string[] */
    private const TERMINAL_STAGES = [
        'Closed Won',
        'Closed Lost',
        'Chiusa persa',
        'Chiuso Negativamente',
    ];

    private LeadProspectSync $leadProspectSync;

    public function __construct(
        private EntityManager $entityManager,
        ?LeadProspectSync $leadProspectSync = null,
    ) {
        $this->leadProspectSync = $leadProspectSync ?? new LeadProspectSync($entityManager);
    }

    /**
     * @return array{opportunitiesClosed: int, leadsUpdated: int, opportunityIds: string[]}
     */
    public function syncFromCall(Entity $call): array
    {
        if ($call->getEntityType() !== 'Call') {
            return $this->emptyResult();
        }

        if (!in_array($call->get('status'), ['Held', 'Not Held'], true)) {
            return $this->emptyResult();
        }

        if ($call->get('esito') !== self::ESITO_NON_INTERESSATO) {
            return $this->emptyResult();
        }

        $closeDate = $this->resolveCloseDate($call);
        $closedIds = [];

        foreach ($this->resolveOpportunityIds($call) as $opportunityId) {
            if ($this->closeOpportunity($opportunityId, $closeDate)) {
                $closedIds[] = $opportunityId;
            }
        }

        return [
            'opportunitiesClosed' => count($closedIds),
            'leadsUpdated' => $this->markLinkedLeadLost($call),
            'opportunityIds' => $closedIds,
        ];
    }

    /**
     * @return array{opportunitiesClosed: int, leadsUpdated: int, opportunityIds: string[]}
     */
    private function emptyResult(): array
    {
        return [
            'opportunitiesClosed' => 0,
            'leadsUpdated' => 0,
            'opportunityIds' => [],
        ];
    }

    private function resolveCloseDate(Entity $call): string
    {
        foreach (['data', 'dataRiscontro'] as $field) {
            $value = $call->get($field);

            if (is_string($value) && $value !== '') {
                return substr($value, 0, 10);
            }
        }

        return (new \DateTimeImmutable('now', new \DateTimeZone(BusinessDateTime::BUSINESS_TIMEZONE)))
            ->format('Y-m-d');
    }

    private function closeOpportunity(string $opportunityId, string $closeDate): bool
    {
        $opportunity = $this->entityManager->getEntityById('Opportunity', $opportunityId);

        if (!$opportunity) {
            return false;
        }

        $stage = (string) $opportunity->get('stage');

        if (in_array($stage, self::TERMINAL_STAGES, true)) {
            return false;
        }

        $opportunity->set([
            'stage' => self::STAGE_LOST,
            'probability' => 0,
            'closeDate' => $closeDate,
        ]);

        $this->entityManager->saveEntity($opportunity, [
            'silent' => true,
            'skipAcl' => true,
        ]);

        return true;
    }

    private function markLinkedLeadLost(Entity $call): int
    {
        $leadId = $this->resolveLeadId($call);

        if (!$leadId) {
            return 0;
        }

        $lead = $this->entityManager->getEntityById('Lead', $leadId);

        if (!$lead) {
            return 0;
        }

        $changed = false;

        if ($lead->get('status') !== 'Dead') {
            $lead->set('status', 'Dead');
            $changed = true;
        }

        if ($lead->get('statoGestione') !== 'Trattativa Chiusa') {
            $lead->set('statoGestione', 'Trattativa Chiusa');
            $changed = true;
        }

        if (!$changed) {
            return 0;
        }

        $this->entityManager->saveEntity($lead, [
            'silent' => true,
            'skipAcl' => true,
        ]);

        return 1;
    }

    /**
     * @return string[]
     */
    public function resolveOpportunityIds(Entity $call): array
    {
        $ids = [];

        if ($call->get('parentType') === 'Opportunity' && $call->get('parentId')) {
            $ids[] = (string) $call->get('parentId');
        }

        $appuntamentoIds = $this->resolveAppuntamentoIds($call);

        foreach ($appuntamentoIds as $appuntamentoId) {
            $ids = array_merge($ids, $this->findOpportunityIdsBy([
                'appuntamentoId' => $appuntamentoId,
            ]));
        }

        $leadId = $this->resolveLeadId($call);

        if ($leadId) {
            $ids = array_merge($ids, $this->findOpportunityIdsBy([
                'leadId' => $leadId,
            ]));
        }

        $prospectId = $this->resolveProspectId($call);

        if ($prospectId) {
            $ids = array_merge($ids, $this->findOpportunityIdsBy([
                'prospectId' => $prospectId,
            ]));
        }

        $phone = $this->normalizePhone((string) $call->get('telefono'));

        if ($phone !== '') {
            $ids = array_merge($ids, $this->findOpportunityIdsByPhone($phone));
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * @return string[]
     */
    private function resolveAppuntamentoIds(Entity $call): array
    {
        $ids = [];

        $fromNota = $this->extractAppuntamentoId((string) $call->get('nota'));

        if ($fromNota) {
            $ids[] = $fromNota;
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * @param array<string, mixed> $where
     * @return string[]
     */
    private function findOpportunityIdsBy(array $where): array
    {
        $ids = [];

        $collection = $this->entityManager
            ->getRDBRepository('Opportunity')
            ->select(['id'])
            ->where($where)
            ->find();

        foreach ($collection as $opportunity) {
            $ids[] = $opportunity->getId();
        }

        return $ids;
    }

    /**
     * @return string[]
     */
    private function findOpportunityIdsByPhone(string $normalizedPhone): array
    {
        $ids = [];

        $collection = $this->entityManager
            ->getRDBRepository('Opportunity')
            ->select(['id', 'telefono', 'stage'])
            ->where([
                'telefono!=' => null,
                'stage!=' => self::TERMINAL_STAGES,
            ])
            ->order('createdAt', 'DESC')
            ->limit(0, 300)
            ->find();

        foreach ($collection as $opportunity) {
            $oppPhone = $this->normalizePhone((string) $opportunity->get('telefono'));

            if ($oppPhone !== '' && $oppPhone === $normalizedPhone) {
                $ids[] = $opportunity->getId();
            }
        }

        return $ids;
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

    public function resolveLeadId(Entity $call): ?string
    {
        if ($call->get('parentType') === 'Lead' && $call->get('parentId')) {
            return (string) $call->get('parentId');
        }

        $prospectId = $this->resolveProspectId($call);

        if (!$prospectId) {
            return null;
        }

        $prospect = $this->entityManager->getEntityById('Prospect', $prospectId);

        if (!$prospect) {
            return null;
        }

        $lead = $this->leadProspectSync->findExistingLeadByProspect($prospect);

        return $lead ? $lead->getId() : null;
    }

    public function resolveProspectId(Entity $call): ?string
    {
        if ($call->get('prospectId')) {
            return (string) $call->get('prospectId');
        }

        if ($call->get('parentType') === 'Prospect' && $call->get('parentId')) {
            return (string) $call->get('parentId');
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
}
