<?php

namespace Espo\Custom\Services;

use Espo\Custom\Tools\DateTime\BusinessDateTime;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class CallEsitoOpportunitySync
{
    public const NOTA_PREFIX = 'Auto-Pending-Appuntamento:';
    public const ESITO_NON_INTERESSATO = 'Non interessato';
    private const APPUNTAMENTO_NON_INTERESSATO = 'Non Interessato';
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
    ) {}

  /**
   * @return array{opportunitiesClosed: int, appuntamentiUpdated: int}
   */
    public function syncFromCall(Entity $call): array
    {
        if ($call->getEntityType() !== 'Call') {
            return ['opportunitiesClosed' => 0, 'appuntamentiUpdated' => 0];
        }

        if (!in_array($call->get('status'), ['Held', 'Not Held'], true)) {
            return ['opportunitiesClosed' => 0, 'appuntamentiUpdated' => 0];
        }

        if ($call->get('esito') !== self::ESITO_NON_INTERESSATO) {
            return ['opportunitiesClosed' => 0, 'appuntamentiUpdated' => 0];
        }

        $closeDate = $this->resolveCloseDate($call);

        return [
            'opportunitiesClosed' => $this->closeLinkedOpportunities($call, $closeDate),
            'appuntamentiUpdated' => $this->updatePendingAppuntamento($call),
        ];
    }

    private function resolveCloseDate(Entity $call): string
    {
        $data = $call->get('data');

        if (is_string($data) && $data !== '') {
            return substr($data, 0, 10);
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

    private function updatePendingAppuntamento(Entity $call): int
    {
        $appuntamentoId = $this->extractAppuntamentoId((string) $call->get('nota'));

        if (!$appuntamentoId) {
            return 0;
        }

        $appuntamento = $this->entityManager->getEntityById('Appuntamento', $appuntamentoId);

        if (!$appuntamento) {
            return 0;
        }

        if ($appuntamento->get('sottostato') !== 'Pending') {
            return 0;
        }

        $appuntamento->set('sottostato', self::APPUNTAMENTO_NON_INTERESSATO);

        $this->entityManager->saveEntity($appuntamento, [
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
            $collection = $this->entityManager
                ->getRDBRepository('Opportunity')
                ->select(['id'])
                ->where(['appuntamentoId' => $appuntamentoId])
                ->find();

            foreach ($collection as $opportunity) {
                $ids[] = $opportunity->getId();
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    public function extractAppuntamentoId(string $nota): ?string
    {
        if (!str_contains($nota, self::NOTA_PREFIX)) {
            return null;
        }

        if (!preg_match('/' . preg_quote(self::NOTA_PREFIX, '/') . '\s*([a-z0-9]{17})/i', $nota, $matches)) {
            return null;
        }

        return $matches[1];
    }
}
