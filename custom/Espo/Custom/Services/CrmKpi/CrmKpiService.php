<?php

namespace Espo\Custom\Services\CrmKpi;

use Espo\Custom\Tools\CrmKpi\Alerts;
use Espo\Custom\Tools\CrmKpi\DateRange;
use Espo\Custom\Tools\CrmKpi\KpiContext;
use Espo\Entities\User;
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
        $nettiFinKo = $this->countQuotes($ctx, excludeFinancingKo: true);
        $nettiRecessi = $this->countQuotes($ctx, excludeRecesso: true);

        return (object) [
            'totali' => $totali,
            'nettiFinKo' => $nettiFinKo,
            'nettiRecessi' => $nettiRecessi,
        ];
    }

    private function getValoreProduzioneTile(KpiContext $ctx): object
    {
        $totali = $this->sumQuoteAmount($ctx);
        $netti = $this->sumQuoteAmount($ctx, excludeFinancingKo: true, excludeRecesso: true);
        $koFin = $this->sumQuoteAmount($ctx, onlyFinancingKo: true);
        $recessi = $this->sumQuoteAmount($ctx, onlyRecesso: true);

        return (object) [
            'totali' => round($totali, 2),
            'netti' => round($netti, 2),
            'koFinanziamenti' => round($koFin, 2),
            'recessi' => round($recessi, 2),
        ];
    }

    private function getProvvigioniTile(KpiContext $ctx): object
    {
        $totali = $this->sumQuoteProvvigioni($ctx);
        $netti = $this->sumQuoteProvvigioni($ctx, excludeFinancingKo: true, excludeRecesso: true);
        $koFin = $this->sumQuoteProvvigioni($ctx, onlyFinancingKo: true);
        $recessi = $this->sumQuoteProvvigioni($ctx, onlyRecesso: true);

        return (object) [
            'totali' => round($totali, 2),
            'nette' => round($netti, 2),
            'koFinanziamenti' => round($koFin, 2),
            'koRecessi' => round($recessi, 2),
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
    private function getAlertsSafe(?string $from, ?string $to, ?string $productBrandId): array
    {
        try {
            return (new Alerts($this->entityManager))->build($from, $to, $productBrandId);
        } catch (\Throwable) {
            return [];
        }
    }
}
