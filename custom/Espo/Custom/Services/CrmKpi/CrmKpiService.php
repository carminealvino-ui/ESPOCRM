<?php

namespace Espo\Custom\Services\CrmKpi;

use DateTime;
use Espo\Custom\Tools\CrmKpi\Alerts;
use Espo\Custom\Tools\CrmKpi\DateRange;
use Espo\Custom\Tools\CrmKpi\OpenOpportunityPeriod;
use Espo\Custom\Tools\CrmKpi\WeekOfMonth;
use Espo\Entities\User;
use Espo\ORM\EntityManager;

class CrmKpiService
{
    private const WEEKDAY_LABELS = [
        1 => 'Lun',
        2 => 'Mar',
        3 => 'Mer',
        4 => 'Gio',
        5 => 'Ven',
        6 => 'Sab',
        7 => 'Dom',
    ];

    public function __construct(
        private EntityManager $entityManager,
    ) {}

    public function getSummary(User $user, string $period = 'currentMonth'): object
    {
        try {
            return $this->buildSummary($user, $period);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'CrmKpi getSummary [' . $period . ']: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    private function buildSummary(User $user, string $period): object
    {
        $period = DateRange::normalizePeriod($period);
        [$from, $to, $prevFrom, $prevTo] = DateRange::resolve($period);
        $hasPreviousPeriod = $prevFrom !== null && $prevTo !== null;

        $heldCurrent = $this->countAppuntamentiHeld($from, $to);
        $heldPrevious = $hasPreviousPeriod
            ? $this->countAppuntamentiHeld($prevFrom, $prevTo)
            : null;

        $oppCurrent = $this->countOpportunitiesFromAppointmentsInPeriod($from, $to);
        $oppAmount = $this->sumOpportunitiesFromAppointmentsInPeriod($from, $to);

        $contractsCurrent = $this->countContracts($from, $to);
        $contractsPrevious = $hasPreviousPeriod
            ? $this->countContracts($prevFrom, $prevTo)
            : null;

        $amountCurrent = $this->sumContractAmount($from, $to);
        $amountPrevious = $hasPreviousPeriod
            ? $this->sumContractAmount($prevFrom, $prevTo)
            : null;

        return (object) [
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'tiles' => (object) [
                'appuntamentiSvolti' => (object) [
                    'value' => $heldCurrent,
                    'previous' => $heldPrevious,
                    'changePercent' => $heldPrevious !== null
                        ? $this->percentChange($heldCurrent, $heldPrevious)
                        : null,
                ],
                'opportunitaAperte' => (object) [
                    'count' => $oppCurrent,
                    'amount' => round($oppAmount, 2),
                ],
                'contrattiFirmati' => (object) [
                    'value' => $contractsCurrent,
                    'previous' => $contractsPrevious,
                    'changePercent' => $contractsPrevious !== null
                        ? $this->percentChange($contractsCurrent, $contractsPrevious)
                        : null,
                ],
                'valoreContratti' => (object) [
                    'value' => round($amountCurrent, 2),
                    'previous' => $amountPrevious !== null ? round($amountPrevious, 2) : null,
                    'changePercent' => $amountPrevious !== null
                        ? $this->percentChange($amountCurrent, $amountPrevious)
                        : null,
                ],
            ],
            'funnel' => $this->getFunnelSafe($from, $to),
            'contractsByWeekday' => $this->getContractsByWeekdaySafe($from, $to),
            'contractsByWeekOfMonth' => $this->getContractsByWeekOfMonthSafe($from, $to),
            'alerts' => $this->getAlertsSafe($from, $to),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dateWhere(string $field, ?string $from, ?string $to): array
    {
        $where = [];

        if ($from !== null) {
            $where[$field . '>='] = $from;
        }

        if ($to !== null) {
            $where[$field . '<='] = $to;
        }

        return $where;
    }

    /**
     * @return string[]
     */
    private function getAppuntamentoIdsInPeriod(?string $from, ?string $to): array
    {
        $ids = [];

        $collection = $this->entityManager
            ->getRDBRepository('Appuntamento')
            ->select(['id'])
            ->where($this->dateWhere('dataAppuntamento', $from, $to))
            ->find();

        foreach ($collection as $appuntamento) {
            $ids[] = $appuntamento->getId();
        }

        return $ids;
    }

    private function countAppuntamentiWithOpportunity(?string $from, ?string $to): int
    {
        $appuntamentoIds = $this->getAppuntamentoIdsInPeriod($from, $to);

        if ($appuntamentoIds === []) {
            return 0;
        }

        $withOpportunity = [];

        $collection = $this->entityManager
            ->getRDBRepository('Opportunity')
            ->select(['appuntamentoId'])
            ->where([
                'AND' => [
                    ['appuntamentoId!=' => ''],
                    ['appuntamentoId!=' => null],
                ],
                'appuntamentoId' => $appuntamentoIds,
            ])
            ->find();

        foreach ($collection as $opportunity) {
            $appuntamentoId = $opportunity->get('appuntamentoId');

            if ($appuntamentoId) {
                $withOpportunity[$appuntamentoId] = true;
            }
        }

        return count($withOpportunity);
    }

    private function countOpportunitiesFromAppointmentsInPeriod(?string $from, ?string $to): int
    {
        $appuntamentoIds = $this->getAppuntamentoIdsInPeriod($from, $to);

        if ($appuntamentoIds === []) {
            return 0;
        }

        return (int) $this->entityManager
            ->getRDBRepository('Opportunity')
            ->where([
                'appuntamentoId' => $appuntamentoIds,
            ])
            ->count();
    }

    private function countAppuntamenti(?string $from, ?string $to): int
    {
        return (int) $this->entityManager
            ->getRDBRepository('Appuntamento')
            ->where($this->dateWhere('dataAppuntamento', $from, $to))
            ->count();
    }

    private function countAppuntamentiHeld(?string $from, ?string $to): int
    {
        return (int) $this->entityManager
            ->getRDBRepository('Appuntamento')
            ->where(array_merge(
                ['status' => 'Held'],
                $this->dateWhere('dataAppuntamento', $from, $to)
            ))
            ->count();
    }

    private function sumOpportunitiesFromAppointmentsInPeriod(?string $from, ?string $to): float
    {
        $appuntamentoIds = $this->getAppuntamentoIdsInPeriod($from, $to);

        if ($appuntamentoIds === []) {
            return 0.0;
        }

        return $this->safeSum('Opportunity', [
            'appuntamentoId' => $appuntamentoIds,
        ], [
            'importoOpportunit',
            'amount',
        ]);
    }

    private function countOpenOpportunities(string $period): int
    {
        return (int) $this->entityManager
            ->getRDBRepository('Opportunity')
            ->where(OpenOpportunityPeriod::where($period))
            ->count();
    }

    private function sumOpenOpportunityAmount(string $period): float
    {
        return $this->safeSum('Opportunity', OpenOpportunityPeriod::where($period), [
            'importoOpportunit',
            'amount',
        ]);
    }

    private function countContracts(?string $from, ?string $to): int
    {
        return (int) $this->entityManager
            ->getRDBRepository('Quote')
            ->where($this->dateWhere('dateQuoted', $from, $to))
            ->count();
    }

    private function sumContractAmount(?string $from, ?string $to): float
    {
        return $this->safeSum('Quote', $this->dateWhere('dateQuoted', $from, $to), [
            'importoContratto',
            'amount',
            'grandTotalAmount',
        ]);
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
    private function getFunnelSafe(?string $from, ?string $to): array
    {
        try {
            return $this->getFunnel($from, $to);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return object[]
     */
    private function getContractsByWeekdaySafe(?string $from, ?string $to): array
    {
        try {
            return $this->getContractsByWeekday($from, $to);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return object[]
     */
    private function getFunnel(?string $from, ?string $to): array
    {
        $appuntamenti = $this->countAppuntamenti($from, $to);
        $appuntamentiSvolti = $this->countAppuntamentiWithOpportunity($from, $to);
        $opportunities = $this->countOpportunitiesFromAppointmentsInPeriod($from, $to);
        $contracts = $this->countContracts($from, $to);

        $steps = [
            ['key' => 'appuntamenti', 'label' => 'Appuntamenti', 'value' => $appuntamenti],
            ['key' => 'held', 'label' => 'Appuntamenti svolti', 'value' => $appuntamentiSvolti],
            ['key' => 'opportunity', 'label' => 'Opportunità', 'value' => $opportunities],
            ['key' => 'contracts', 'label' => 'Contratti', 'value' => $contracts],
        ];

        $result = [];
        $base = max($appuntamenti, 1);
        $previousValue = null;

        foreach ($steps as $step) {
            $value = $step['value'];
            $percentOfTotal = round(($value / $base) * 100, 1);
            $percentOfPrevious = null;

            if ($previousValue !== null) {
                $percentOfPrevious = round(($value / max($previousValue, 1)) * 100, 1);
            }

            $result[] = (object) [
                'key' => $step['key'],
                'label' => $step['label'],
                'value' => $value,
                'percentOfHeld' => $percentOfTotal,
                'percentOfPrevious' => $percentOfPrevious,
            ];

            $previousValue = $value;
        }

        return $result;
    }

    /**
     * @return object[]
     */
    private function getContractsByWeekday(?string $from, ?string $to): array
    {
        $counts = array_fill(1, 7, 0);

        $collection = $this->entityManager
            ->getRDBRepository('Quote')
            ->select(['id', 'dateQuoted'])
            ->where($this->dateWhere('dateQuoted', $from, $to))
            ->find();

        foreach ($collection as $quote) {
            $dateQuoted = $quote->get('dateQuoted');

            if (!$dateQuoted) {
                continue;
            }

            $weekday = (int) (new DateTime($dateQuoted))->format('N');

            $counts[$weekday]++;
        }

        $max = max($counts) ?: 1;
        $total = array_sum($counts);
        $totalBase = max($total, 1);
        $result = [];

        foreach ([1, 2, 3, 4, 5, 6, 7] as $day) {
            $result[] = (object) [
                'day' => $day,
                'label' => self::WEEKDAY_LABELS[$day],
                'value' => $counts[$day],
                'widthPercent' => round(($counts[$day] / $max) * 100, 1),
                'percentOfTotal' => round(($counts[$day] / $totalBase) * 100, 1),
            ];
        }

        return $result;
    }

    /**
     * @return object[]
     */
    private function getContractsByWeekOfMonthSafe(?string $from, ?string $to): array
    {
        try {
            $this->ensureWeekOfMonthLoaded();

            return $this->getContractsByWeekOfMonth($from, $to);
        } catch (\Throwable $e) {
            error_log('CrmKpi contractsByWeekOfMonth: ' . $e->getMessage());

            return [];
        }
    }

    private function ensureWeekOfMonthLoaded(): void
    {
        if (class_exists(WeekOfMonth::class)) {
            return;
        }

        $path = dirname(__DIR__) . '/Tools/CrmKpi/WeekOfMonth.php';

        if (is_file($path)) {
            require_once $path;
        }
    }

    /**
     * @return object[]
     */
    private function getContractsByWeekOfMonth(?string $from, ?string $to): array
    {
        $counts = [];

        $collection = $this->entityManager
            ->getRDBRepository('Quote')
            ->select(['id', 'dateQuoted'])
            ->where($this->dateWhere('dateQuoted', $from, $to))
            ->find();

        foreach ($collection as $quote) {
            $weekIndex = WeekOfMonth::resolveIndexForDate($quote->get('dateQuoted'));

            if ($weekIndex === null) {
                continue;
            }

            if (!isset($counts[$weekIndex])) {
                $counts[$weekIndex] = 0;
            }

            $counts[$weekIndex]++;
        }

        $weeks = $this->resolveWeekOfMonthLabels($from, $to);

        return WeekOfMonth::buildChartRows($counts, $weeks);
    }

    /**
     * @return array<int, array{index: int, start: string, end: string, label: string}>
     */
    private function resolveWeekOfMonthLabels(?string $from, ?string $to): array
    {
        if ($from && $to && substr($from, 0, 7) === substr($to, 0, 7)) {
            $weeks = WeekOfMonth::validWeeksInMonth(
                (int) substr($from, 0, 4),
                (int) substr($from, 5, 2)
            );

            if ($weeks !== []) {
                return $weeks;
            }
        }

        return WeekOfMonth::validWeeksForRange($from, $to);
    }

    /**
     * @return object[]
     */
    private function getAlertsSafe(?string $from, ?string $to): array
    {
        try {
            return (new Alerts($this->entityManager))->build($from, $to);
        } catch (\Throwable) {
            return [];
        }
    }

    private function percentChange($current, $previous)
    {
        if ((float) $previous === 0.0) {
            return (float) $current > 0 ? 100.0 : 0.0;
        }

        return round((((float) $current - (float) $previous) / (float) $previous) * 100, 1);
    }
}
