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
    public const NOTA_PREFIX = 'Auto-Pending-Appuntamento:';
    public const ESITO_NON_INTERESSATO = 'Non interessato';
    private const STAGE_LOST = 'Closed Lost';

    /** @var string[] */
    private const TERMINAL_STAGES = [
        'Closed Won',
        'Closed Lost',
        'Chiusa persa',
        'Chiuso Negativamente',
    ];

    public function __construct(
        private EntityManager $entityManager,
        private LeadProspectSync $leadProspectSync,
    ) {}

    /**
     * @return array{opportunitiesClosed: int, leadsUpdated: int}
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

        return [
            'opportunitiesClosed' => $this->closeLinkedOpportunities($call, $closeDate),
            'leadsUpdated' => $this->markLinkedLeadLost($call),
        ];
    }

    /**
     * @return array{opportunitiesClosed: int, leadsUpdated: int}
     */
    private function emptyResult(): array
    {
        return [
            'opportunitiesClosed' => 0,
            'leadsUpdated' => 0,
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

    private function closeLinkedOpportunities(Entity $call, string $closeDate): int
    {
        $closed = 0;

        foreach ($this->resolveOpportunityIds($call) as $opportunityId) {
            if ($this->closeOpportunity($opportunityId, $closeDate)) {
                $closed++;
            }
        }

        return $closed;
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
            'closeDate' => $closeDate,
        ]);

        $this->entityManager->saveEntity($opportunity, [
            'silent' => true,
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

        $appuntamentoId = $this->extractAppuntamentoId((string) $call->get('nota'));

        if ($appuntamentoId) {
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
