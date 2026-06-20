<?php

namespace Espo\Custom\Services\CrmKpi;

use DateTime;
use Espo\Core\Utils\DateTime as DateTimeUtil;
use Espo\Custom\Tools\CrmKpi\DateRange;
use Espo\Custom\Tools\CrmKpi\OpenOpportunityPeriod;
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
        [$from, $to, $prevFrom, $prevTo] = DateRange::resolve($period);

        $heldCurrent = $this->countAppuntamentiHeld($from, $to);
        $heldPrevious = $this->countAppuntamentiHeld($prevFrom, $prevTo);

        $oppCurrent = $this->countOpenOpportunities($period);
        $oppAmount = $this->sumOpenOpportunityAmount($period);

        $contractsCurrent = $this->countContracts($from, $to);
        $contractsPrevious = $this->countContracts($prevFrom, $prevTo);

        $amountCurrent = $this->sumContractAmount($from, $to);
        $amountPrevious = $this->sumContractAmount($prevFrom, $prevTo);

        return (object) [
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'tiles' => (object) [
                'appuntamentiSvolti' => (object) [
                    'value' => $heldCurrent,
                    'previous' => $heldPrevious,
                    'changePercent' => $this->percentChange($heldCurrent, $heldPrevious),
                ],
                'opportunitaAperte' => (object) [
                    'count' => $oppCurrent,
                    'amount' => round($oppAmount, 2),
                ],
                'contrattiFirmati' => (object) [
                    'value' => $contractsCurrent,
                    'previous' => $contractsPrevious,
                    'changePercent' => $this->percentChange($contractsCurrent, $contractsPrevious),
                ],
                'valoreContratti' => (object) [
                    'value' => round($amountCurrent, 2),
                    'previous' => round($amountPrevious, 2),
                    'changePercent' => $this->percentChange($amountCurrent, $amountPrevious),
                ],
            ],
            'funnel' => $this->getFunnelSafe($from, $to),
            'contractsByWeekday' => $this->getContractsByWeekdaySafe($from, $to),
            'alerts' => $this->getAlertsSafe(),
        ];
    }

    /**
     * @return string[]
     */
    private function getAppuntamentoIdsInPeriod(string $from, string $to): array
    {
        $ids = [];

        $collection = $this->entityManager
            ->getRDBRepository('Appuntamento')
            ->select(['id'])
            ->where([
                'dataAppuntamento>=' => $from,
                'dataAppuntamento<=' => $to,
            ])
            ->find();

        foreach ($collection as $appuntamento) {
            $ids[] = $appuntamento->getId();
        }

        return $ids;
    }

    private function countAppuntamentiWithOpportunity(string $from, string $to): int
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

    private function countOpportunitiesFromAppointmentsInPeriod(string $from, string $to): int
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

    private function countAppuntamenti(string $from, string $to): int
    {
        return (int) $this->entityManager
            ->getRDBRepository('Appuntamento')
            ->where([
                'dataAppuntamento>=' => $from,
                'dataAppuntamento<=' => $to,
            ])
            ->count();
    }

    private function countAppuntamentiHeld(string $from, string $to): int
    {
        return (int) $this->entityManager
            ->getRDBRepository('Appuntamento')
            ->where([
                'status' => 'Held',
                'dataAppuntamento>=' => $from,
                'dataAppuntamento<=' => $to,
            ])
            ->count();
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

    private function countContracts(string $from, string $to): int
    {
        return (int) $this->entityManager
            ->getRDBRepository('Quote')
            ->where([
                'dateQuoted>=' => $from,
                'dateQuoted<=' => $to,
            ])
            ->count();
    }

    private function sumContractAmount(string $from, string $to): float
    {
        $where = [
            'dateQuoted>=' => $from,
            'dateQuoted<=' => $to,
        ];

        return $this->safeSum('Quote', $where, [
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
    private function getFunnelSafe(string $from, string $to): array
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
    private function getContractsByWeekdaySafe(string $from, string $to): array
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
    private function getFunnel(string $from, string $to): array
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
    private function getContractsByWeekday(string $from, string $to): array
    {
        $counts = array_fill(1, 7, 0);

        $collection = $this->entityManager
            ->getRDBRepository('Quote')
            ->select(['id', 'dateQuoted'])
            ->where([
                'dateQuoted>=' => $from,
                'dateQuoted<=' => $to,
            ])
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
        $result = [];

        foreach ([1, 2, 3, 4, 5, 6, 7] as $day) {
            $result[] = (object) [
                'day' => $day,
                'label' => self::WEEKDAY_LABELS[$day],
                'value' => $counts[$day],
                'widthPercent' => round(($counts[$day] / $max) * 100, 1),
            ];
        }

        return $result;
    }

    /**
     * @return object[]
     */
    private function getAlertsSafe(): array
    {
        try {
            return $this->getAlerts();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return object[]
     */
    private function getAlerts(): array
    {
        $now = (new DateTime())->format(DateTimeUtil::SYSTEM_DATE_TIME_FORMAT);
        $threeDaysAgo = (new DateTime('-3 days'))->format(DateTimeUtil::SYSTEM_DATE_FORMAT);

        $pendingWithoutOpp = 0;
        $pendingCollection = $this->entityManager
            ->getRDBRepository('Appuntamento')
            ->select(['id'])
            ->where([
                'status' => 'Held',
                'sottostato' => 'Pending',
                'dataAppuntamento<=' => $threeDaysAgo,
            ])
            ->limit(0, 100)
            ->find();

        foreach ($pendingCollection as $appuntamento) {
            $opportunity = $this->entityManager
                ->getRDBRepository('Opportunity')
                ->where(['appuntamentoId' => $appuntamento->getId()])
                ->findOne();

            if (!$opportunity) {
                $pendingWithoutOpp++;
            }
        }

        $negotiationWithoutContract = 0;
        $negotiationCollection = $this->entityManager
            ->getRDBRepository('Opportunity')
            ->select(['id'])
            ->where([
                'stage' => ['Proposal', 'Negotiation'],
            ])
            ->limit(0, 200)
            ->find();

        foreach ($negotiationCollection as $opportunity) {
            if (!$this->opportunityHasQuote($opportunity->getId())) {
                $negotiationWithoutContract++;
            }
        }

        $callsOverdue = (int) $this->entityManager
            ->getRDBRepository('Call')
            ->where([
                'status' => 'Planned',
                'dateStart<' => $now,
            ])
            ->count();

        return [
            (object) [
                'key' => 'pendingNoOpportunity',
                'label' => 'Appuntamenti Pending senza opportunità (>3 gg)',
                'value' => $pendingWithoutOpp,
                'link' => '#Appuntamento',
            ],
            (object) [
                'key' => 'negotiationNoContract',
                'label' => 'Opportunità in trattativa senza contratto',
                'value' => $negotiationWithoutContract,
                'link' => '#Opportunity',
            ],
            (object) [
                'key' => 'callsOverdue',
                'label' => 'Contatti telefonici pianificati scaduti',
                'value' => $callsOverdue,
                'link' => '#Call',
            ],
        ];
    }

    private function opportunityHasQuote(string $opportunityId): bool
    {
        foreach (['opportunitaId', 'opportunityId'] as $attribute) {
            try {
                $quote = $this->entityManager
                    ->getRDBRepository('Quote')
                    ->where([$attribute => $opportunityId])
                    ->findOne();

                if ($quote) {
                    return true;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return false;
    }

    private function percentChange(float|int $current, float|int $previous): ?float
    {
        if ((float) $previous === 0.0) {
            return (float) $current > 0 ? 100.0 : 0.0;
        }

        return round((((float) $current - (float) $previous) / (float) $previous) * 100, 1);
    }
}
