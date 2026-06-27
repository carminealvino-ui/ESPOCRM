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
            'yieldsByWeekday' => $this->getYieldsByWeekdaySafe($ctx),
            'yieldsByWeek' => $this->getYieldsByWeekSafe($ctx),
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
     * @return string[]
     */
    private function getAppuntamentoIds(KpiContext $ctx): array
    {
        $ids = [];

        $collection = $this->entityManager
            ->getRDBRepository('Appuntamento')
            ->select(['id'])
            ->where(array_merge(
                $ctx->appuntamentoWhere(),
                $this->notAnnullatoWhere()
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
            return $this->countOpportunitiesPending($this->getAppuntamentoIds($ctx), $ctx);
        }

        $appuntamentoIds = $this->getAppuntamentoIds($ctx);

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
        return $this->safeSum(
            'Quote',
            $this->quoteFilterWhere($ctx, $excludeFinancingKo, $excludeRecesso, $onlyFinancingKo, $onlyRecesso),
            ['totaleProvvigioni']
        );
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
            return array_merge($where, ['statoContratto' => 'Recesso']);
        }

        if ($onlyFinancingKo) {
            return array_merge($where, [
                'finanziamento' => true,
                'statoFinanziamento' => 'Respinto',
            ]);
        }

        if ($excludeRecesso) {
            $where['statoContratto!='] = 'Recesso';
        }

        if ($excludeFinancingKo) {
            $where[] = [
                'OR' => [
                    ['finanziamento' => false],
                    ['finanziamento' => null],
                    ['statoFinanziamento!=' => 'Respinto'],
                    ['statoFinanziamento' => null],
                    ['statoFinanziamento' => ''],
                ],
            ];
        }

        return $where;
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
    private function getYieldsByWeekdaySafe(KpiContext $ctx): array
    {
        try {
            $aggregated = $this->aggregateAppuntamentiYields($ctx);

            return YieldBuilder::buildWeekdayRows(
                $aggregated['weekday']['lordi'],
                $aggregated['weekday']['netti']
            );
        } catch (\Throwable) {
            return YieldBuilder::emptyWeekdayRows();
        }
    }

    /**
     * @return object[]
     */
    private function getYieldsByWeekSafe(KpiContext $ctx): array
    {
        try {
            $aggregated = $this->aggregateAppuntamentiYields($ctx);

            return YieldBuilder::buildWeekRows(
                $aggregated['week']['lordi'],
                $aggregated['week']['netti'],
                $aggregated['week']['weeks']
            );
        } catch (\Throwable) {
            return YieldBuilder::emptyWeekRows();
        }
    }

    /**
     * @return array{
     *   weekday: array{lordi: array<int, int>, netti: array<int, int>},
     *   week: array{lordi: array<int, int>, netti: array<int, int>, weeks: array<int, array<string, mixed>>}
     * }
     */
    private function aggregateAppuntamentiYields(KpiContext $ctx): array
    {
        $weekdayLordi = array_fill(1, 7, 0);
        $weekdayNetti = array_fill(1, 7, 0);
        $weekLordi = [];
        $weekNetti = [];
        $weeks = WeekOfMonth::validWeeksForRange($ctx->from, $ctx->to);

        $collection = $this->entityManager
            ->getRDBRepository('Appuntamento')
            ->select(['status', 'sottostato', 'esito', 'dataAppuntamento', 'dateStart'])
            ->where($ctx->appuntamentoWhere())
            ->find();

        foreach ($collection as $appuntamento) {
            $date = $this->resolveAppuntamentoDate($appuntamento);

            if (!$date) {
                continue;
            }

            $weekday = (int) (new \DateTimeImmutable($date))->format('N');
            $weekdayLordi[$weekday]++;

            if ($this->isAppuntamentoNetto($appuntamento)) {
                $weekdayNetti[$weekday]++;
            }

            $weekIndex = WeekOfMonth::resolveIndexForDate($date);

            if ($weekIndex === null) {
                continue;
            }

            $weekLordi[$weekIndex] = ($weekLordi[$weekIndex] ?? 0) + 1;

            if ($this->isAppuntamentoNetto($appuntamento)) {
                $weekNetti[$weekIndex] = ($weekNetti[$weekIndex] ?? 0) + 1;
            }
        }

        return [
            'weekday' => [
                'lordi' => $weekdayLordi,
                'netti' => $weekdayNetti,
            ],
            'week' => [
                'lordi' => $weekLordi,
                'netti' => $weekNetti,
                'weeks' => $weeks,
            ],
        ];
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
