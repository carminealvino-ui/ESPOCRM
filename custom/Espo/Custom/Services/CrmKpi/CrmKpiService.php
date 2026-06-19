<?php

namespace Espo\Custom\Services\CrmKpi;

use DateTime;
use Espo\Core\Utils\DateTime as DateTimeUtil;
use Espo\Custom\Tools\CrmKpi\DateRange;
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

        $oppCurrent = $this->countOpenOpportunities();
        $oppAmount = $this->sumOpenOpportunityAmount();

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
            'funnel' => $this->getFunnel($from, $to),
            'contractsByWeekday' => $this->getContractsByWeekday($from, $to),
            'alerts' => $this->getAlerts(),
        ];
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

    private function countOpenOpportunities(): int
    {
        return (int) $this->entityManager
            ->getRDBRepository('Opportunity')
            ->where([
                'stage!=' => ['Closed Won', 'Closed Lost'],
            ])
            ->count();
    }

    private function sumOpenOpportunityAmount(): float
    {
        $sum = $this->entityManager
            ->getRDBRepository('Opportunity')
            ->where([
                'stage!=' => ['Closed Won', 'Closed Lost'],
            ])
            ->sum('importoOpportunit');

        if ($sum > 0) {
            return (float) $sum;
        }

        return (float) $this->entityManager
            ->getRDBRepository('Opportunity')
            ->where([
                'stage!=' => ['Closed Won', 'Closed Lost'],
            ])
            ->sum('amount');
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
        $sum = $this->entityManager
            ->getRDBRepository('Quote')
            ->where([
                'dateQuoted>=' => $from,
                'dateQuoted<=' => $to,
            ])
            ->sum('importoContratto');

        if ($sum > 0) {
            return (float) $sum;
        }

        return (float) $this->entityManager
            ->getRDBRepository('Quote')
            ->where([
                'dateQuoted>=' => $from,
                'dateQuoted<=' => $to,
            ])
            ->sum('amount');
    }

    /**
     * @return object[]
     */
    private function getFunnel(string $from, string $to): array
    {
        $held = $this->countAppuntamentiHeld($from, $to);

        $withOpportunity = (int) $this->entityManager
            ->getRDBRepository('Opportunity')
            ->where([
                'appuntamentoId!=' => null,
                'createdAt>=' => $from . ' 00:00:00',
                'createdAt<=' => $to . ' 23:59:59',
            ])
            ->count();

        $closedWon = (int) $this->entityManager
            ->getRDBRepository('Opportunity')
            ->where([
                'stage' => 'Closed Won',
                'closeDate>=' => $from,
                'closeDate<=' => $to,
            ])
            ->count();

        $contracts = $this->countContracts($from, $to);

        $steps = [
            ['key' => 'held', 'label' => 'Appuntamenti svolti', 'value' => $held],
            ['key' => 'opportunity', 'label' => 'Opportunità create', 'value' => $withOpportunity],
            ['key' => 'closedWon', 'label' => 'Opportunità vinte', 'value' => $closedWon],
            ['key' => 'contracts', 'label' => 'Contratti', 'value' => $contracts],
        ];

        $result = [];
        $base = max($held, 1);

        foreach ($steps as $step) {
            $result[] = (object) [
                'key' => $step['key'],
                'label' => $step['label'],
                'value' => $step['value'],
                'percentOfHeld' => round(($step['value'] / $base) * 100, 1),
            ];
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
            $quote = $this->entityManager
                ->getRDBRepository('Quote')
                ->where(['opportunitaId' => $opportunity->getId()])
                ->findOne();

            if (!$quote) {
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

    private function percentChange(float|int $current, float|int $previous): ?float
    {
        if ((float) $previous === 0.0) {
            return (float) $current > 0 ? 100.0 : 0.0;
        }

        return round((((float) $current - (float) $previous) / (float) $previous) * 100, 1);
    }
}
