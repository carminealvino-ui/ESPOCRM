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
        'Appuntamento non in agenda',
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

    private const CONTRACT_SOSPESO = 'Sospeso';

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
        $opportunita = $this->getOpportunitaTile($ctx, (int) $appuntamenti->netti);
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
                (float) $appuntamenti->totali,
                (float) $appuntamenti->lordi,
                (float) $appuntamenti->netti,
                (float) $contratti->lordi,
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
        // kpi-periodo-andwhere-v1:
        // Totali = SOLO nel periodo dashlet (mese/trimestre/…), esclusi Pianificati e Rifissati.
        // Non usare array_merge su WHERE con chiavi 'OR': cancella il filtro date.
        $totali = $this->countAppuntamentiTotali($ctx);
        // Lordi = Totali - Annullati
        $lordi = $this->countAppuntamentiLordi($ctx);
        $annullati = max($totali - $lordi, 0);
        $ingestibili = min($this->countAppuntamentiIngestibili($ctx), $lordi);
        // Netti = Lordi - Ingestibili
        $netti = max($lordi - $ingestibili, 0);

        return (object) [
            'totali' => $totali,
            'annullati' => $annullati,
            'lordi' => $lordi,
            'ingestibili' => $ingestibili,
            'netti' => $netti,
        ];
    }

    private function getOpportunitaTile(KpiContext $ctx, int $nettiAppuntamenti): object
    {
        // Netti appuntamenti = base opportunità
        $totali = $nettiAppuntamenti;
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
        // Allineato ad Appuntamenti: Totali → Recessi → Lordi=Totali−Recessi → … → Netti
        $totali = $this->countQuotes($ctx);
        $recessi = $this->countQuotes($ctx, onlyRecesso: true);
        $lordi = $this->countQuotes($ctx, excludeRecesso: true);
        $finanziamentiRifiutati = $this->countQuotes($ctx, onlyFinancingKo: true, excludeRecesso: true);
        $sospesi = $this->countQuotes($ctx, onlySospesi: true, excludeRecesso: true);
        $netti = $this->countQuotes(
            $ctx,
            excludeFinancingKo: true,
            excludeRecesso: true,
            excludeSospesi: true
        );

        return (object) [
            'totali' => $totali,
            'recessi' => $recessi,
            'lordi' => $lordi,
            'finanziamentiRifiutati' => $finanziamentiRifiutati,
            'sospesi' => $sospesi,
            'netti' => $netti,
        ];
    }

    private function getValoreProduzioneTile(KpiContext $ctx): object
    {
        $totali = $this->sumQuoteAmount($ctx);
        $recessi = $this->sumQuoteAmount($ctx, onlyRecesso: true);
        $lordi = $this->sumQuoteAmount($ctx, excludeRecesso: true);
        $finanziamentiRifiutati = $this->sumQuoteAmount($ctx, onlyFinancingKo: true, excludeRecesso: true);
        $sospesi = $this->sumQuoteAmount($ctx, onlySospesi: true, excludeRecesso: true);
        $netti = $this->sumQuoteAmount(
            $ctx,
            excludeFinancingKo: true,
            excludeRecesso: true,
            excludeSospesi: true
        );

        return (object) [
            'totali' => round($totali, 2),
            'recessi' => round($recessi, 2),
            'lordi' => round($lordi, 2),
            'finanziamentiRifiutati' => round($finanziamentiRifiutati, 2),
            'sospesi' => round($sospesi, 2),
            'netti' => round($netti, 2),
        ];
    }

    private function getProvvigioniTile(KpiContext $ctx): object
    {
        $totali = $this->sumQuoteProvvigioni($ctx);
        $recessi = $this->sumQuoteProvvigioni($ctx, onlyRecesso: true);
        $lordi = $this->sumQuoteProvvigioni($ctx, excludeRecesso: true);
        $finanziamentiRifiutati = $this->sumQuoteProvvigioni($ctx, onlyFinancingKo: true, excludeRecesso: true);
        $sospesi = $this->sumQuoteProvvigioni($ctx, onlySospesi: true, excludeRecesso: true);
        $netti = $this->sumQuoteProvvigioni(
            $ctx,
            excludeFinancingKo: true,
            excludeRecesso: true,
            excludeSospesi: true
        );

        return (object) [
            'totali' => round($totali, 2),
            'recessi' => round($recessi, 2),
            'lordi' => round($lordi, 2),
            'finanziamentiRifiutati' => round($finanziamentiRifiutati, 2),
            'sospesi' => round($sospesi, 2),
            'netti' => round($netti, 2),
        ];
    }

    private function countAppuntamentiTotali(KpiContext $ctx): int
    {
        // Totali = SOLO periodo selezionato, esclusi Pianificati e Rifissati.
        // Usa combineWhere: array_merge su due 'OR' cancellava il filtro date.
        return (int) $this->entityManager
            ->getRDBRepository('Appuntamento')
            ->where($this->appuntamentiBaseWhere($ctx))
            ->count();
    }

    private function countAppuntamentiLordi(KpiContext $ctx): int
    {
        // Lordi = Totali - Annullati
        return (int) $this->entityManager
            ->getRDBRepository('Appuntamento')
            ->where($this->combineWhere(
                $this->appuntamentiBaseWhere($ctx),
                $this->notAnnullatoWhere()
            ))
            ->count();
    }

    private function countAppuntamentiIngestibili(KpiContext $ctx): int
    {
        return (int) $this->entityManager
            ->getRDBRepository('Appuntamento')
            ->where($this->combineWhere(
                $this->appuntamentiBaseWhere($ctx),
                $this->notAnnullatoWhere(),
                ['status' => 'Ingestibile']
            ))
            ->count();
    }

    private function countAppuntamentiNetti(KpiContext $ctx): int
    {
        // Derivato in getAppuntamentiTile; tenuto per aggregazioni
        return (int) $this->entityManager
            ->getRDBRepository('Appuntamento')
            ->where($this->combineWhere(
                $this->appuntamentiBaseWhere($ctx),
                $this->notAnnullatoWhere(),
                ['status!=' => 'Ingestibile']
            ))
            ->count();
    }

    /**
     * Base comune tile Appuntamenti: periodo + esclusione Pianificati/Rifissati.
     *
     * @return array<string, mixed>
     */
    private function appuntamentiBaseWhere(KpiContext $ctx): array
    {
        return $this->combineWhere(
            $ctx->appuntamentoWhere(),
            $this->notPianificatoWhere(),
            $this->notRifissatoWhere(),
            $this->notGestitoWhere()
        );
    }

    /**
     * Compone clausole WHERE senza array_merge: due chiavi 'OR'/'AND' in merge
     * sovrascrivono il filtro periodo e contano TUTTA la storia.
     *
     * @param array<string, mixed> ...$parts
     * @return array<string, mixed>
     */
    private function combineWhere(array ...$parts): array
    {
        $clauses = [];

        foreach ($parts as $part) {
            if ($part === []) {
                continue;
            }

            $clauses[] = $part;
        }

        if ($clauses === []) {
            return [];
        }

        if (count($clauses) === 1) {
            return $clauses[0];
        }

        return ['AND' => $clauses];
    }

    /**
     * @return array<string, mixed>
     */
    private function notPianificatoWhere(): array
    {
        return ['status!=' => 'Planned'];
    }

    /**
     * @return array<string, mixed>
     */
    private function notRifissatoWhere(): array
    {
        return [
            'OR' => [
                ['sottostato!=' => 'Rifissato'],
                ['sottostato' => null],
                ['sottostato' => ''],
            ],
        ];
    }

    /**
     * Gestito (Ripasso / non in agenda) escluso dal monitoraggio.
     *
     * @return array<string, mixed>
     */
    private function notGestitoWhere(): array
    {
        return [
            'OR' => [
                ['sottostato!=' => 'Gestito'],
                ['sottostato' => null],
                ['sottostato' => ''],
            ],
        ];
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
     * Appuntamenti netti = lordi - ingestibili (esclusi Rifissati e Annullati).
     *
     * @return string[]
     */
    private function getNetAppuntamentoIds(KpiContext $ctx): array
    {
        $ids = [];

        $collection = $this->entityManager
            ->getRDBRepository('Appuntamento')
            ->select(['id'])
            ->where($this->combineWhere(
                $this->appuntamentiBaseWhere($ctx),
                $this->notAnnullatoWhere(),
                ['status!=' => 'Ingestibile']
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
        bool $onlyRecesso = false,
        bool $onlySospesi = false,
        bool $excludeSospesi = false
    ): int {
        if (
            $onlyFinancingKo
            || $excludeFinancingKo
            || $onlyRecesso
            || $excludeRecesso
            || $onlySospesi
            || $excludeSospesi
        ) {
            return $this->countQuotesResolved(
                $ctx,
                $excludeFinancingKo,
                $excludeRecesso,
                $onlyFinancingKo,
                $onlyRecesso,
                $onlySospesi,
                $excludeSospesi
            );
        }

        return (int) $this->entityManager
            ->getRDBRepository('Quote')
            ->where($this->quoteFilterWhere(
                $ctx,
                $excludeFinancingKo,
                $excludeRecesso,
                $onlyFinancingKo,
                $onlyRecesso,
                $onlySospesi,
                $excludeSospesi
            ))
            ->count();
    }

    private function sumQuoteAmount(
        KpiContext $ctx,
        bool $excludeFinancingKo = false,
        bool $excludeRecesso = false,
        bool $onlyFinancingKo = false,
        bool $onlyRecesso = false,
        bool $onlySospesi = false,
        bool $excludeSospesi = false
    ): float {
        if (
            $onlyFinancingKo
            || $excludeFinancingKo
            || $onlyRecesso
            || $excludeRecesso
            || $onlySospesi
            || $excludeSospesi
        ) {
            return $this->sumQuoteFieldResolved(
                $ctx,
                ['importoContratto', 'amount', 'grandTotalAmount'],
                $excludeFinancingKo,
                $excludeRecesso,
                $onlyFinancingKo,
                $onlyRecesso,
                $onlySospesi,
                $excludeSospesi
            );
        }

        return $this->safeSum(
            'Quote',
            $this->quoteFilterWhere(
                $ctx,
                $excludeFinancingKo,
                $excludeRecesso,
                $onlyFinancingKo,
                $onlyRecesso,
                $onlySospesi,
                $excludeSospesi
            ),
            ['importoContratto', 'amount', 'grandTotalAmount']
        );
    }

    private function sumQuoteProvvigioni(
        KpiContext $ctx,
        bool $excludeFinancingKo = false,
        bool $excludeRecesso = false,
        bool $onlyFinancingKo = false,
        bool $onlyRecesso = false,
        bool $onlySospesi = false,
        bool $excludeSospesi = false
    ): float {
        if (
            $onlyFinancingKo
            || $excludeFinancingKo
            || $onlyRecesso
            || $excludeRecesso
            || $onlySospesi
            || $excludeSospesi
        ) {
            return $this->sumQuoteFieldResolved(
                $ctx,
                ['totaleProvvigioni'],
                $excludeFinancingKo,
                $excludeRecesso,
                $onlyFinancingKo,
                $onlyRecesso,
                $onlySospesi,
                $excludeSospesi
            );
        }

        return $this->safeSum(
            'Quote',
            $this->quoteFilterWhere(
                $ctx,
                $excludeFinancingKo,
                $excludeRecesso,
                $onlyFinancingKo,
                $onlyRecesso,
                $onlySospesi,
                $excludeSospesi
            ),
            ['totaleProvvigioni']
        );
    }

    private function countQuotesResolved(
        KpiContext $ctx,
        bool $excludeFinancingKo = false,
        bool $excludeRecesso = false,
        bool $onlyFinancingKo = false,
        bool $onlyRecesso = false,
        bool $onlySospesi = false,
        bool $excludeSospesi = false
    ): int {
        return count($this->filterQuotesForTile(
            $ctx,
            $excludeFinancingKo,
            $excludeRecesso,
            $onlyFinancingKo,
            $onlyRecesso,
            $onlySospesi,
            $excludeSospesi
        ));
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
        bool $onlyRecesso = false,
        bool $onlySospesi = false,
        bool $excludeSospesi = false
    ): float {
        $quotes = $this->filterQuotesForTile(
            $ctx,
            $excludeFinancingKo,
            $excludeRecesso,
            $onlyFinancingKo,
            $onlyRecesso,
            $onlySospesi,
            $excludeSospesi
        );
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
        bool $onlyRecesso = false,
        bool $onlySospesi = false,
        bool $excludeSospesi = false
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
                $onlyRecesso,
                $onlySospesi,
                $excludeSospesi
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
        bool $onlySospesi,
        bool $excludeSospesi,
    ): bool {
        $isRecesso = $this->isQuoteRecesso($quote);
        $isFinancingRejected = $this->isQuoteFinancingRejected($quote, $opportunityRejectedMap);
        $isSospeso = $this->isQuoteSospeso($quote);

        if ($onlyRecesso) {
            return $isRecesso;
        }

        if ($excludeRecesso && $isRecesso) {
            return false;
        }

        if ($onlyFinancingKo) {
            return $isFinancingRejected;
        }

        if ($onlySospesi) {
            return $isSospeso;
        }

        if ($excludeFinancingKo && $isFinancingRejected) {
            return false;
        }

        if ($excludeSospesi && $isSospeso) {
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
        bool $onlyRecesso = false,
        bool $onlySospesi = false,
        bool $excludeSospesi = false
    ): array {
        $where = $ctx->quoteWhere();

        if ($onlyRecesso) {
            return array_merge($where, ['statoContratto' => self::CONTRACT_RECESSO]);
        }

        if ($onlyFinancingKo) {
            return array_merge($where, $this->financingRejectedWhere());
        }

        if ($onlySospesi) {
            return array_merge($where, ['statoContratto' => self::CONTRACT_SOSPESO]);
        }

        $excludedStates = [];

        if ($excludeRecesso) {
            $excludedStates[] = self::CONTRACT_RECESSO;
        }

        if ($excludeSospesi) {
            $excludedStates[] = self::CONTRACT_SOSPESO;
        }

        if ($excludedStates !== []) {
            $where['statoContratto!='] = $excludedStates;
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

    private function isQuoteSospeso(Entity $quote, ?Entity $opportunity = null): bool
    {
        if ($this->isQuoteRecesso($quote, $opportunity)) {
            return false;
        }

        if ($quote->get('statoContratto') === self::CONTRACT_SOSPESO) {
            return true;
        }

        if ($opportunity !== null) {
            return $opportunity->get('statoContratto') === self::CONTRACT_SOSPESO;
        }

        $opportunityId = $quote->get('opportunitaId');

        if (!$opportunityId) {
            return false;
        }

        $linkedOpportunity = $this->entityManager->getEntityById('Opportunity', $opportunityId);

        return $linkedOpportunity
            && !$this->isQuoteRecesso($quote, $linkedOpportunity)
            && $linkedOpportunity->get('statoContratto') === self::CONTRACT_SOSPESO;
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

        $collection = $this->entityManager
            ->getRDBRepository('Appuntamento')
            ->where($ctx->appuntamentoWhere())
            ->find();

        foreach ($collection as $appuntamento) {
            $date = $this->resolveAppuntamentoDate($appuntamento);

            if (!$date || !$this->isDateInPeriod($date, $ctx)) {
                continue;
            }

            $weekday = (int) (new \DateTimeImmutable($date))->format('N');
            $weekIndex = WeekOfMonth::resolveIndexForDate($date);

            if (
                !$this->isAppuntamentoPianificato($appuntamento)
                && !$this->isAppuntamentoRifissato($appuntamento)
                && !$this->isAppuntamentoGestito($appuntamento)
            ) {
                $weekdayBuckets[$weekday]['appuntamentiTotali']++;
            }

            if ($this->isAppuntamentoLordo($appuntamento)) {
                $weekdayBuckets[$weekday]['appuntamentiLordi']++;
            }

            if ($this->isAppuntamentoNetto($appuntamento)) {
                $weekdayBuckets[$weekday]['appuntamentiNetti']++;
            }

            if ($weekIndex !== null && isset($weekBuckets[$weekIndex])) {
                if (
                    !$this->isAppuntamentoPianificato($appuntamento)
                    && !$this->isAppuntamentoRifissato($appuntamento)
                    && !$this->isAppuntamentoGestito($appuntamento)
                ) {
                    $weekBuckets[$weekIndex]['appuntamentiTotali']++;
                }

                if ($this->isAppuntamentoLordo($appuntamento)) {
                    $weekBuckets[$weekIndex]['appuntamentiLordi']++;
                }

                if ($this->isAppuntamentoNetto($appuntamento)) {
                    $weekBuckets[$weekIndex]['appuntamentiNetti']++;
                }
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
        if ($this->isQuoteRecesso($quote)) {
            return false;
        }

        if ($this->isQuoteFinancingRejected($quote, $opportunityRejectedMap)) {
            return false;
        }

        if ($this->isQuoteSospeso($quote)) {
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

    /**
     * Sicurezza: anche se la WHERE SQL perde il periodo, le rese restano nel range.
     */
    private function isDateInPeriod(string $date, KpiContext $ctx): bool
    {
        if ($ctx->from !== null && $date < $ctx->from) {
            return false;
        }

        if ($ctx->to !== null && $date > $ctx->to) {
            return false;
        }

        return true;
    }

    private function isAppuntamentoPianificato(Entity $appuntamento): bool
    {
        return $appuntamento->get('status') === 'Planned';
    }

    private function isAppuntamentoRifissato(Entity $appuntamento): bool
    {
        return $appuntamento->get('sottostato') === 'Rifissato';
    }

    private function isAppuntamentoGestito(Entity $appuntamento): bool
    {
        return $appuntamento->get('sottostato') === 'Gestito';
    }

    private function isAppuntamentoLordo(Entity $appuntamento): bool
    {
        if (
            $this->isAppuntamentoPianificato($appuntamento)
            || $this->isAppuntamentoRifissato($appuntamento)
            || $this->isAppuntamentoGestito($appuntamento)
        ) {
            return false;
        }

        return $this->isAppuntamentoNotAnnullato($appuntamento);
    }

    private function isAppuntamentoNetto(Entity $appuntamento): bool
    {
        if (!$this->isAppuntamentoLordo($appuntamento)) {
            return false;
        }

        return $appuntamento->get('status') !== 'Ingestibile';
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
            $built = (new Alerts($this->entityManager))->build($from, $to, $productBrandId);

            return $built->criticita ?? [];
        } catch (\Throwable) {
            return [];
        }
    }
}
