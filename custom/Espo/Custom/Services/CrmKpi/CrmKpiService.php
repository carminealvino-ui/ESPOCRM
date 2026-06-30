<?php

namespace Espo\Custom\Services\CrmKpi;

use Espo\Custom\Tools\CrmKpi\Alerts;
use Espo\Custom\Tools\CrmKpi\DateRange;
use Espo\Custom\Tools\CrmKpi\FunnelBuilder;
use Espo\Custom\Tools\CrmKpi\KpiContext;
use Espo\Custom\Tools\CrmKpi\WeekOfMonth;
use Espo\Custom\Tools\CrmKpi\YieldBuilder;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class CrmKpiService
{
    private const ID_CHUNK_SIZE = 500;

    /** @var array<string, mixed>|null */
    private ?array $periodPipelineCache = null;

  /** @var string[] */
    private const ESITI_ANNULLATI = [
        'Annullato dal Potenziale',
        'Annullato dal Consulente',
        'Annullato Azienda',
        'Annullato Call Center',
    ];

    /** @var string[] */
    private const STAGE_WON = [
        'Closed Won',
        'Chiuso Positivamente',
    ];

    /** @var string[] */
    private const STAGE_LOST = [
        'Closed Lost',
        'Chiusa persa',
        'Chiuso Negativamente',
    ];

    private const FINANCING_REJECTED_STATES = [
        'Respinto',
    ];

    private const CONTRACT_RECESSO = 'Recesso';

    public function __construct(
        private EntityManager $entityManager,
    ) {}

    public function getSummary(
        User $user,
        string $period = 'currentMonth',
        ?string $productBrandId = null
    ): object {
        try {
            return $this->buildSummary($user, $period, $productBrandId);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'CrmKpi getSummary [' . $period . ']: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    private function buildSummary(User $user, string $period, ?string $productBrandId): object
    {
        $this->periodPipelineCache = null;

        $period = DateRange::normalizePeriod($period);
        [$from, $to] = DateRange::resolve($period);
        $ctx = new KpiContext($from, $to, $this->normalizeBrandId($productBrandId));

        $appuntamenti = $this->getAppuntamentiTile($ctx);
        $opportunita = $this->getOpportunitaTile($ctx);
        $contratti = $this->getContrattiTile($ctx);
        $valore = $this->getValoreProduzioneTile($ctx);
        $provvigioni = $this->getProvvigioniTile($ctx);

        return (object) [
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'productBrandId' => $ctx->productBrandId,
            'productBrandName' => $this->resolveBrandName($ctx->productBrandId),
            'tiles' => (object) [
                'appuntamenti' => $appuntamenti,
                'opportunita' => $opportunita,
                'contratti' => $contratti,
                'valoreProduzione' => $valore,
                'provvigioni' => $provvigioni,
            ],
            'salesPipeline' => FunnelBuilder::buildSalesPipeline(
                (float) $appuntamenti->lordi,
                (float) $appuntamenti->netti,
                (float) $opportunita->totali,
                (float) $contratti->totali,
                (float) $contratti->netti
            ),
            'yieldsByWeekday' => $this->buildYieldsByWeekday($ctx),
            'yieldsByWeek' => $this->buildYieldsByWeek($ctx),
            'yieldColumns' => YieldBuilder::pipelineColumns(),
            'alerts' => $this->getAlertsSafe($from, $to, $ctx->productBrandId),
        ];
    }

    private function normalizeBrandId(?string $productBrandId): ?string
    {
        $productBrandId = trim((string) $productBrandId);

        return $productBrandId !== '' ? $productBrandId : null;
    }

    private function resolveBrandName(?string $productBrandId): ?string
    {
        if (!$productBrandId) {
            return null;
        }

        $brand = $this->entityManager->getEntityById('ProductBrand', $productBrandId);

        return $brand ? (string) $brand->get('name') : null;
    }

    private function getAppuntamentiTile(KpiContext $ctx): object
    {
        $lordi = $this->countAppuntamentiLordi($ctx);
        $totali = $this->countAppuntamentiTotali($ctx);
        $annullati = max($lordi - $totali, 0);
        $ingestibili = $this->countAppuntamentiIngestibili($ctx);
        $netti = $this->countAppuntamentiNetti($ctx);

        return (object) [
            'lordi' => $lordi,
            'annullati' => $annullati,
            'totali' => $totali,
            'ingestibili' => $ingestibili,
            'netti' => $netti,
        ];
    }

    private function getOpportunitaTile(KpiContext $ctx): object
    {
        $totali = $this->countOpportunities($ctx);
        $concluse = $this->countOpportunities($ctx, won: true);
        $pending = $this->countOpportunities($ctx, pending: true);
        $perse = $this->countOpportunities($ctx, lost: true);

        return (object) [
            'totali' => $totali,
            'concluse' => $concluse,
            'pending' => $pending,
            'perse' => $perse,
        ];
    }

    private function getContrattiTile(KpiContext $ctx): object
    {
        $totali = $this->countQuotes($ctx);
        $finanziamentiRifiutati = $this->countQuotes($ctx, onlyFinancingKo: true);
        $lordi = $this->countQuotes($ctx, excludeRecesso: true);
        $recessi = $this->countQuotes($ctx, onlyRecesso: true);
        $netti = $this->countQuotes($ctx, excludeFinancingKo: true, excludeRecesso: true);

        return (object) [
            'totali' => $totali,
            'finanziamentiRifiutati' => $finanziamentiRifiutati,
            'lordi' => $lordi,
            'recessi' => $recessi,
            'netti' => $netti,
        ];
    }

    private function getValoreProduzioneTile(KpiContext $ctx): object
    {
        $totali = $this->sumQuoteAmount($ctx);
        $finanziamentiRifiutati = $this->sumQuoteAmount($ctx, onlyFinancingKo: true);
        $lordi = $this->sumQuoteAmount($ctx, excludeRecesso: true);
        $recessi = $this->sumQuoteAmount($ctx, onlyRecesso: true);
        $netti = $this->sumQuoteAmount($ctx, excludeFinancingKo: true, excludeRecesso: true);

        return (object) [
            'totali' => round($totali, 2),
            'finanziamentiRifiutati' => round($finanziamentiRifiutati, 2),
            'lordi' => round($lordi, 2),
            'recessi' => round($recessi, 2),
            'netti' => round($netti, 2),
        ];
    }

    private function getProvvigioniTile(KpiContext $ctx): object
    {
        $totali = $this->sumQuoteProvvigioni($ctx);
        $finanziamentiRifiutati = $this->sumQuoteProvvigioni($ctx, onlyFinancingKo: true);
        $lordi = $this->sumQuoteProvvigioni($ctx, excludeRecesso: true);
        $recessi = $this->sumQuoteProvvigioni($ctx, onlyRecesso: true);
        $netti = $this->sumQuoteProvvigioni($ctx, excludeFinancingKo: true, excludeRecesso: true);

        return (object) [
            'totali' => round($totali, 2),
            'finanziamentiRifiutati' => round($finanziamentiRifiutati, 2),
            'lordi' => round($lordi, 2),
            'recessi' => round($recessi, 2),
            'netti' => round($netti, 2),
        ];
    }

    private function countAppuntamentiTotali(KpiContext $ctx): int
    {
        return (int) $this->entityManager
            ->getRDBRepository('Appuntamento')
            ->where(array_merge(
                $ctx->appuntamentoWhere(),
                $this->notAnnullatoWhere()
            ))
            ->count();
    }

    private function countAppuntamentiLordi(KpiContext $ctx): int
    {
        return (int) $this->entityManager
            ->getRDBRepository('Appuntamento')
            ->where($ctx->appuntamentoWhere())
            ->count();
    }

    private function countAppuntamentiIngestibili(KpiContext $ctx): int
    {
        return (int) $this->entityManager
            ->getRDBRepository('Appuntamento')
            ->where(array_merge(
                $ctx->appuntamentoWhere(),
                $this->notAnnullatoWhere(),
                ['status' => 'Ingestibile']
            ))
            ->count();
    }

    private function countAppuntamentiNetti(KpiContext $ctx): int
    {
        return (int) $this->entityManager
            ->getRDBRepository('Appuntamento')
            ->where(array_merge(
                $ctx->appuntamentoWhere(),
                $this->notAnnullatoWhere(),
                ['status!=' => 'Ingestibile'],
                ['status' => 'Held']
            ))
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function notAnnullatoWhere(): array
    {
        return [
            'AND' => [
                [
                    'OR' => [
                        ['sottostato!=' => 'Annullato'],
                        ['sottostato' => null],
                        ['sottostato' => ''],
                    ],
                ],
                [
                    'OR' => [
                        ['esito' => null],
                        ['esito' => ''],
                        ['esito!=' => self::ESITI_ANNULLATI],
                    ],
                ],
            ],
        ];
    }

    /**
     * Appuntamenti netti (svolti, non ingestibili) per opportunità e pipeline.
     *
     * @return string[]
     */
    private function getNetAppuntamentoIds(KpiContext $ctx): array
    {
        $ids = [];

        $collection = $this->entityManager
            ->getRDBRepository('Appuntamento')
            ->select(['id'])
            ->where(array_merge(
                $ctx->appuntamentoWhere(),
                $this->notAnnullatoWhere(),
                ['status!=' => 'Ingestibile'],
                ['status' => 'Held']
            ))
            ->find();

        foreach ($collection as $appuntamento) {
            $ids[] = $appuntamento->getId();
        }

        return $ids;
    }

    private function countOpportunities(
        KpiContext $ctx,
        bool $won = false,
        bool $pending = false,
        bool $lost = false
    ): int {
        if ($pending) {
            return $this->countOpportunitiesPending($this->getNetAppuntamentoIds($ctx), $ctx);
        }

        $appuntamentoIds = $this->getNetAppuntamentoIds($ctx);

        if ($appuntamentoIds === []) {
            return 0;
        }

        $where = [
            'appuntamentoId' => $appuntamentoIds,
        ];

        if ($ctx->productBrandId) {
            $where['productBrandId'] = $ctx->productBrandId;
        }

        if ($won) {
            $where['stage'] = self::STAGE_WON;
        } elseif ($lost) {
            $where['stage'] = self::STAGE_LOST;
        }

        return (int) $this->entityManager
            ->getRDBRepository('Opportunity')
            ->where($where)
            ->count();
    }

    /**
     * @param string[] $appuntamentoIds
     */
    private function countOpportunitiesPending(array $appuntamentoIds, KpiContext $ctx): int
    {
        $pendingAppuntamentoIds = $this->getPendingAppuntamentoIds($appuntamentoIds);

        if ($pendingAppuntamentoIds === []) {
            return 0;
        }

        $where = [
            'appuntamentoId' => $pendingAppuntamentoIds,
        ];

        if ($ctx->productBrandId) {
            $where['productBrandId'] = $ctx->productBrandId;
        }

        return (int) $this->entityManager
            ->getRDBRepository('Opportunity')
            ->where($where)
            ->count();
    }

    /**
     * @param string[] $appuntamentoIds
     * @return string[]
     */
    private function getPendingAppuntamentoIds(array $appuntamentoIds): array
    {
        $ids = [];

        $collection = $this->entityManager
            ->getRDBRepository('Appuntamento')
            ->select(['id'])
            ->where([
                'id' => $appuntamentoIds,
                'sottostato' => 'Pending',
            ])
            ->find();

        foreach ($collection as $appuntamento) {
            $ids[] = $appuntamento->getId();
        }

        return $ids;
    }

    private function countQuotes(
        KpiContext $ctx,
        bool $excludeFinancingKo = false,
        bool $excludeRecesso = false,
        bool $onlyFinancingKo = false,
        bool $onlyRecesso = false
    ): int {
        if ($onlyFinancingKo || $excludeFinancingKo) {
            return $this->countQuotesResolved($ctx, $excludeFinancingKo, $excludeRecesso, $onlyFinancingKo, $onlyRecesso);
        }

        return (int) $this->entityManager
            ->getRDBRepository('Quote')
            ->where($this->quoteFilterWhere($ctx, $excludeFinancingKo, $excludeRecesso, $onlyFinancingKo, $onlyRecesso))
            ->count();
    }

    private function sumQuoteAmount(
        KpiContext $ctx,
        bool $excludeFinancingKo = false,
        bool $excludeRecesso = false,
        bool $onlyFinancingKo = false,
        bool $onlyRecesso = false
    ): float {
        if ($onlyFinancingKo || $excludeFinancingKo) {
            return $this->sumQuoteFieldResolved(
                $ctx,
                ['importoContratto', 'amount', 'grandTotalAmount'],
                $excludeFinancingKo,
                $excludeRecesso,
                $onlyFinancingKo,
                $onlyRecesso
            );
        }

        return $this->safeSum(
            'Quote',
            $this->quoteFilterWhere($ctx, $excludeFinancingKo, $excludeRecesso, $onlyFinancingKo, $onlyRecesso),
            ['importoContratto', 'amount', 'grandTotalAmount']
        );
    }

    private function sumQuoteProvvigioni(
        KpiContext $ctx,
        bool $excludeFinancingKo = false,
        bool $excludeRecesso = false,
        bool $onlyFinancingKo = false,
        bool $onlyRecesso = false
    ): float {
        if ($onlyFinancingKo || $excludeFinancingKo) {
            return $this->sumQuoteFieldResolved(
                $ctx,
                ['totaleProvvigioni'],
                $excludeFinancingKo,
                $excludeRecesso,
                $onlyFinancingKo,
                $onlyRecesso
            );
        }

        return $this->safeSum(
            'Quote',
            $this->quoteFilterWhere($ctx, $excludeFinancingKo, $excludeRecesso, $onlyFinancingKo, $onlyRecesso),
            ['totaleProvvigioni']
        );
    }

    private function countQuotesResolved(
        KpiContext $ctx,
        bool $excludeFinancingKo = false,
        bool $excludeRecesso = false,
        bool $onlyFinancingKo = false,
        bool $onlyRecesso = false
    ): int {
        return count($this->filterQuotesForTile($ctx, $excludeFinancingKo, $excludeRecesso, $onlyFinancingKo, $onlyRecesso));
    }

    /**
     * @param string[] $attributes
     */
    private function sumQuoteFieldResolved(
        KpiContext $ctx,
        array $attributes,
        bool $excludeFinancingKo = false,
        bool $excludeRecesso = false,
        bool $onlyFinancingKo = false,
        bool $onlyRecesso = false
    ): float {
        $quotes = $this->filterQuotesForTile($ctx, $excludeFinancingKo, $excludeRecesso, $onlyFinancingKo, $onlyRecesso);
        $sum = 0.0;

        foreach ($quotes as $quote) {
            foreach ($attributes as $attribute) {
                $value = $quote->get($attribute);

                if ($value !== null && $value !== '' && (float) $value !== 0.0) {
                    $sum += (float) $value;
                    break;
                }
            }
        }

        return $sum;
    }

    /**
     * @return Entity[]
     */
    private function filterQuotesForTile(
        KpiContext $ctx,
        bool $excludeFinancingKo = false,
        bool $excludeRecesso = false,
        bool $onlyFinancingKo = false,
        bool $onlyRecesso = false
    ): array {
        $collection = $this->entityManager
            ->getRDBRepository('Quote')
            ->where($ctx->quoteWhere())
            ->find();

        $opportunityIds = [];

        foreach ($collection as $quote) {
            $opportunityId = $quote->get('opportunitaId');

            if ($opportunityId) {
                $opportunityIds[] = $opportunityId;
            }
        }

        $opportunityRejectedMap = $this->loadOpportunityFinancingRejectedMap($opportunityIds);
        $matched = [];

        foreach ($collection as $quote) {
            if ($this->quoteMatchesTileFilter(
                $quote,
                $opportunityRejectedMap,
                $excludeFinancingKo,
                $excludeRecesso,
                $onlyFinancingKo,
                $onlyRecesso
            )) {
                $matched[] = $quote;
            }
        }

        return $matched;
    }

    /**
     * @param array<string, bool> $opportunityRejectedMap
     */
    private function quoteMatchesTileFilter(
        Entity $quote,
        array $opportunityRejectedMap,
        bool $excludeFinancingKo,
        bool $excludeRecesso,
        bool $onlyFinancingKo,
        bool $onlyRecesso,
    ): bool {
        $isRecesso = $quote->get('statoContratto') === self::CONTRACT_RECESSO;
        $isFinancingRejected = $this->isQuoteFinancingRejected($quote, $opportunityRejectedMap);

        if ($onlyRecesso) {
            return $isRecesso;
        }

        if ($onlyFinancingKo) {
            return $isFinancingRejected;
        }

        if ($excludeRecesso && $isRecesso) {
            return false;
        }

        if ($excludeFinancingKo && $isFinancingRejected) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function quoteFilterWhere(
        KpiContext $ctx,
        bool $excludeFinancingKo = false,
        bool $excludeRecesso = false,
        bool $onlyFinancingKo = false,
        bool $onlyRecesso = false
    ): array {
        $where = $ctx->quoteWhere();

        if ($onlyRecesso) {
            return array_merge($where, ['statoContratto' => self::CONTRACT_RECESSO]);
        }

        if ($onlyFinancingKo) {
            return array_merge($where, $this->financingRejectedWhere());
        }

        if ($excludeRecesso) {
            $where['statoContratto!='] = self::CONTRACT_RECESSO;
        }

        if ($excludeFinancingKo) {
            $where[] = $this->excludeFinancingRejectedWhere();
        }

        return $where;
    }

    /**
     * Finanziamento respinto: solo Respinto e mai su contratti in recesso
     * (il recesso annulla il finanziamento senza richiesta/rifiuto bancario).
     *
     * @return array<string, mixed>
     */
    private function financingRejectedWhere(): array
    {
        return [
            'AND' => [
                ['statoContratto!=' => self::CONTRACT_RECESSO],
                [
                    'OR' => [
                        ['statoFinanziamento' => self::FINANCING_REJECTED_STATES],
                        ['opportunita.statoFinanziamento' => self::FINANCING_REJECTED_STATES],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function excludeFinancingRejectedWhere(): array
    {
        return [
            'NOT' => $this->financingRejectedWhere(),
        ];
    }

    private function isFinancingRejectedState(?string $state): bool
    {
        return $state !== null
            && $state !== ''
            && in_array($state, self::FINANCING_REJECTED_STATES, true);
    }

    private function isQuoteRecesso(Entity $quote, ?Entity $opportunity = null): bool
    {
        if ($quote->get('statoContratto') === self::CONTRACT_RECESSO) {
            return true;
        }

        if ($opportunity !== null) {
            return $opportunity->get('statoContratto') === self::CONTRACT_RECESSO;
        }

        $opportunityId = $quote->get('opportunitaId');

        if (!$opportunityId) {
            return false;
        }

        $linkedOpportunity = $this->entityManager->getEntityById('Opportunity', $opportunityId);

        return $linkedOpportunity
            && $linkedOpportunity->get('statoContratto') === self::CONTRACT_RECESSO;
    }

    /**
     * @param array<string, bool>|null $opportunityRejectedMap
     */
    private function isQuoteFinancingRejected(Entity $quote, ?array $opportunityRejectedMap = null): bool
    {
        if ($this->isQuoteRecesso($quote)) {
            return false;
        }

        if ($this->isFinancingRejectedState($quote->get('statoFinanziamento'))) {
            return true;
        }

        $opportunityId = $quote->get('opportunitaId');

        if (!$opportunityId) {
            return false;
        }

        if ($opportunityRejectedMap !== null) {
            return $opportunityRejectedMap[$opportunityId] ?? false;
        }

        $opportunity = $this->entityManager->getEntityById('Opportunity', $opportunityId);

        if (!$opportunity || $this->isQuoteRecesso($quote, $opportunity)) {
            return false;
        }

        return $this->isFinancingRejectedState($opportunity->get('statoFinanziamento'));
    }

    /**
     * @param string[] $opportunityIds
     * @return array<string, bool>
     */
    private function loadOpportunityFinancingRejectedMap(array $opportunityIds): array
    {
        $opportunityIds = array_values(array_unique(array_filter($opportunityIds)));

        if ($opportunityIds === []) {
            return [];
        }

        $map = [];

        $collection = $this->entityManager
            ->getRDBRepository('Opportunity')
            ->select(['id', 'statoContratto', 'statoFinanziamento'])
            ->where(['id' => $opportunityIds])
            ->find();

        foreach ($collection as $opportunity) {
            $map[$opportunity->getId()] = $opportunity->get('statoContratto') !== self::CONTRACT_RECESSO
                && $this->isFinancingRejectedState($opportunity->get('statoFinanziamento'));
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $where
     * @param string[] $attributes
     */
    private function safeSum(string $entityType, array $where, array $attributes): float
    {
        foreach ($attributes as $attribute) {
            try {
                $sum = $this->entityManager
                    ->getRDBRepository($entityType)
                    ->where($where)
                    ->sum($attribute);

                if ($sum !== null && (float) $sum !== 0.0) {
                    return (float) $sum;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return 0.0;
    }

    /**
     * @return object[]
     */
    private function buildYieldsByWeekday(KpiContext $ctx): array
    {
        try {
            $aggregated = $this->getPeriodPipelines($ctx);

            return YieldBuilder::buildWeekdayRows($aggregated['weekday']);
        } catch (\Throwable $e) {
            $this->logYieldError('weekday', $e);

            return YieldBuilder::emptyWeekdayRows();
        }
    }

    /**
     * @return object[]
     */
    private function buildYieldsByWeek(KpiContext $ctx): array
    {
        try {
            $aggregated = $this->getPeriodPipelines($ctx);

            return YieldBuilder::buildWeekRows(
                $aggregated['week'],
                $aggregated['weeks']
            );
        } catch (\Throwable $e) {
            $this->logYieldError('week', $e);

            return YieldBuilder::emptyWeekRows();
        }
    }

    /**
     * @return array{
     *   weekday: array<int, array<string, int>>,
     *   week: array<int, array<string, int>>,
     *   weeks: array<int, array<string, mixed>>
     * }
     */
    private function getPeriodPipelines(KpiContext $ctx): array
    {
        if ($this->periodPipelineCache !== null) {
            return $this->periodPipelineCache;
        }

        $this->periodPipelineCache = $this->aggregatePeriodPipelines($ctx);

        return $this->periodPipelineCache;
    }

    private function logYieldError(string $scope, \Throwable $e): void
    {
        error_log('CrmKpi yieldsBy' . $scope . ': ' . $e->getMessage());
    }

    /**
     * @return array{
     *   weekday: array<int, array<string, int>>,
     *   week: array<int, array<string, int>>,
     *   weeks: array<int, array<string, mixed>>
     * }
     */
    private function aggregatePeriodPipelines(KpiContext $ctx): array
    {
        $weekdayBuckets = $this->initWeekdayBuckets();
        $weekBuckets = [];
        $weeks = WeekOfMonth::validWeeksForRange($ctx->from, $ctx->to);

        foreach (array_keys($weeks) as $weekIndex) {
            $weekBuckets[$weekIndex] = YieldBuilder::emptyMetrics();
        }

        $appuntamentoIdsForOpportunita = [];

        $collection = $this->entityManager
            ->getRDBRepository('Appuntamento')
            ->where($ctx->appuntamentoWhere())
            ->find();

        foreach ($collection as $appuntamento) {
            $date = $this->resolveAppuntamentoDate($appuntamento);

            if (!$date) {
                continue;
            }

            if ($this->isAppuntamentoNetto($appuntamento)) {
                $appuntamentoIdsForOpportunita[] = $appuntamento->getId();
            }

            $weekday = (int) (new \DateTimeImmutable($date))->format('N');
            $weekIndex = WeekOfMonth::resolveIndexForDate($date);

            $weekdayBuckets[$weekday]['appuntamentiLordi']++;

            if ($this->isAppuntamentoNetto($appuntamento)) {
                $weekdayBuckets[$weekday]['appuntamentiNetti']++;
            }

            if ($weekIndex !== null && isset($weekBuckets[$weekIndex])) {
                $weekBuckets[$weekIndex]['appuntamentiLordi']++;

                if ($this->isAppuntamentoNetto($appuntamento)) {
                    $weekBuckets[$weekIndex]['appuntamentiNetti']++;
                }
            }
        }

        if ($appuntamentoIdsForOpportunita !== []) {
            try {
                $this->aggregateOpportunitiesByAppuntamento(
                    $ctx,
                    $appuntamentoIdsForOpportunita,
                    $weekdayBuckets,
                    $weekBuckets
                );
            } catch (\Throwable $e) {
                $this->logYieldError('opportunities', $e);
            }
        }

        try {
            $this->aggregateQuotesByDate($ctx, $weekdayBuckets, $weekBuckets);
        } catch (\Throwable $e) {
            $this->logYieldError('quotes', $e);
        }

        return [
            'weekday' => $weekdayBuckets,
            'week' => $weekBuckets,
            'weeks' => $weeks,
        ];
    }

    /**
     * @return array<int, array<string, int>>
     */
    private function initWeekdayBuckets(): array
    {
        $buckets = [];

        for ($day = 1; $day <= 7; $day++) {
            $buckets[$day] = YieldBuilder::emptyMetrics();
        }

        return $buckets;
    }

    /**
     * @param string[] $appuntamentoIds
     * @param array<int, array<string, int>> $weekdayBuckets
     * @param array<int, array<string, int>> $weekBuckets
     */
    private function aggregateOpportunitiesByAppuntamento(
        KpiContext $ctx,
        array $appuntamentoIds,
        array &$weekdayBuckets,
        array &$weekBuckets,
    ): void {
        foreach (array_chunk($appuntamentoIds, self::ID_CHUNK_SIZE) as $idChunk) {
            $appuntamentoDates = $this->loadAppuntamentoDates($idChunk);

            $where = ['appuntamentoId' => $idChunk];

            if ($ctx->productBrandId) {
                $where['productBrandId'] = $ctx->productBrandId;
            }

            $collection = $this->entityManager
                ->getRDBRepository('Opportunity')
                ->where($where)
                ->find();

            foreach ($collection as $opportunity) {
                $appuntamentoId = $opportunity->get('appuntamentoId');
                $date = $appuntamentoDates[$appuntamentoId] ?? null;

                if (!$date) {
                    continue;
                }

                $weekday = (int) (new \DateTimeImmutable($date))->format('N');
                $weekIndex = WeekOfMonth::resolveIndexForDate($date);

                $weekdayBuckets[$weekday]['opportunita']++;

                if ($weekIndex !== null && isset($weekBuckets[$weekIndex])) {
                    $weekBuckets[$weekIndex]['opportunita']++;
                }
            }
        }
    }

    /**
     * @param array<int, array<string, int>> $weekdayBuckets
     * @param array<int, array<string, int>> $weekBuckets
     */
    private function aggregateQuotesByDate(
        KpiContext $ctx,
        array &$weekdayBuckets,
        array &$weekBuckets,
    ): void {
        $collection = $this->entityManager
            ->getRDBRepository('Quote')
            ->where($ctx->quoteWhere())
            ->find();

        $opportunityIds = [];

        foreach ($collection as $quote) {
            $opportunityId = $quote->get('opportunitaId');

            if ($opportunityId) {
                $opportunityIds[] = $opportunityId;
            }
        }

        $opportunityRejectedMap = $this->loadOpportunityFinancingRejectedMap($opportunityIds);

        foreach ($collection as $quote) {
            $date = $quote->get('dateQuoted');

            if (!$date) {
                continue;
            }

            $date = substr((string) $date, 0, 10);
            $weekday = (int) (new \DateTimeImmutable($date))->format('N');
            $weekIndex = WeekOfMonth::resolveIndexForDate($date);

            $weekdayBuckets[$weekday]['contratti']++;

            if ($this->isQuoteNetto($quote, $opportunityRejectedMap)) {
                $weekdayBuckets[$weekday]['contrattiNetti']++;
            }

            if ($weekIndex !== null && isset($weekBuckets[$weekIndex])) {
                $weekBuckets[$weekIndex]['contratti']++;

                if ($this->isQuoteNetto($quote, $opportunityRejectedMap)) {
                    $weekBuckets[$weekIndex]['contrattiNetti']++;
                }
            }
        }
    }

    /**
     * @param string[] $appuntamentoIds
     * @return array<string, string>
     */
    private function loadAppuntamentoDates(array $appuntamentoIds): array
    {
        $dates = [];

        foreach (array_chunk($appuntamentoIds, self::ID_CHUNK_SIZE) as $idChunk) {
            $collection = $this->entityManager
                ->getRDBRepository('Appuntamento')
                ->where(['id' => $idChunk])
                ->find();

            foreach ($collection as $appuntamento) {
                $date = $this->resolveAppuntamentoDate($appuntamento);

                if ($date) {
                    $dates[$appuntamento->getId()] = $date;
                }
            }
        }

        return $dates;
    }

    /**
     * @param array<string, bool>|null $opportunityRejectedMap
     */
    private function isQuoteNetto(Entity $quote, ?array $opportunityRejectedMap = null): bool
    {
        if ($quote->get('statoContratto') === self::CONTRACT_RECESSO) {
            return false;
        }

        if ($this->isQuoteFinancingRejected($quote, $opportunityRejectedMap)) {
            return false;
        }

        return true;
    }

    private function resolveAppuntamentoDate(Entity $appuntamento): ?string
    {
        $date = $appuntamento->get('dataAppuntamento');

        if ($date) {
            return substr((string) $date, 0, 10);
        }

        $dateStart = $appuntamento->get('dateStart');

        if (!$dateStart) {
            return null;
        }

        return substr((string) $dateStart, 0, 10);
    }

    private function isAppuntamentoNetto(Entity $appuntamento): bool
    {
        if (!$this->isAppuntamentoNotAnnullato($appuntamento)) {
            return false;
        }

        if ($appuntamento->get('status') === 'Ingestibile') {
            return false;
        }

        return $appuntamento->get('status') === 'Held';
    }

    private function isAppuntamentoNotAnnullato(Entity $appuntamento): bool
    {
        $sottostato = $appuntamento->get('sottostato');

        if ($sottostato === 'Annullato') {
            return false;
        }

        $esito = $appuntamento->get('esito');

        if ($esito && in_array($esito, self::ESITI_ANNULLATI, true)) {
            return false;
        }

        return true;
    }

    /**
     * @return object[]
     */
    private function getAlertsSafe(?string $from, ?string $to, ?string $productBrandId): array
    {
        try {
            return (new Alerts($this->entityManager))->build($from, $to, $productBrandId);
        } catch (\Throwable) {
            return [];
        }
    }
}
