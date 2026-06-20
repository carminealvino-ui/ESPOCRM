<?php

namespace Espo\Custom\Tools\CrmKpi;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class Alerts
{
    /** @var string[] */
    private const FINANCING_BACKLOG_STATES = [
        'In lavorazione',
        'In rivalutazione',
        'In Attesa Documentazione',
        'Respinto',
    ];

    public function __construct(
        private EntityManager $entityManager,
    ) {}

    /**
     * @return object[]
     */
    public function build(?string $from, ?string $to): array
    {
        $contractsInPayment = $this->countContractsInPayment($from, $to);

        return [
            $this->alert(
                'opportunityWithoutPhoneFollowUp',
                'Opportunità senza riscontro telefonico',
                $this->countOpportunitiesWithoutPhoneFollowUp(),
                '#Opportunity/filter/senzaRiscontroTelefonico'
            ),
            $this->alert(
                'phoneContactsTodo',
                'Contatti telefonici (call + richiami da fare)',
                $this->countPhoneContactsTodo(),
                '#Call/filter/contattiDaFare'
            ),
            $this->alert(
                'contractsBacklog',
                'Contratti in backlog (sospesi/sospesi per finanziamento)',
                $this->countContractsBacklog(),
                '#Opportunity/filter/contrattiBacklog'
            ),
            $this->alert(
                'contractsInProgress',
                'Contratti in lavorazione',
                $this->countContractsInProgress(),
                '#Opportunity/filter/contrattiInLavorazione'
            ),
            $this->alert(
                'contractsInPayment',
                'Contratti in pagamento (installato nel periodo)',
                $contractsInPayment['count'],
                '#Opportunity/filter/installatoPeriodo',
                $contractsInPayment['provvigioniMeta']
            ),
        ];
    }

    private function alert(
        string $key,
        string $label,
        int $value,
        string $link,
        ?string $meta = null
    ): object {
        $item = (object) [
            'key' => $key,
            'label' => $label,
            'value' => $value,
            'link' => $link,
        ];

        if ($meta !== null && $meta !== '') {
            $item->meta = $meta;
        }

        return $item;
    }

    private function countOpportunitiesWithoutPhoneFollowUp(): int
    {
        $count = 0;

        $collection = $this->entityManager
            ->getRDBRepository('Opportunity')
            ->select(['id', 'leadId', 'prospectId'])
            ->where([
                'AND' => [
                    ['stage!=' => 'Closed Won'],
                    ['stage!=' => 'Closed Lost'],
                ],
            ])
            ->limit(0, 500)
            ->find();

        foreach ($collection as $opportunity) {
            if (!$this->opportunityHasCompletedCall($opportunity)) {
                $count++;
            }
        }

        return $count;
    }

    private function opportunityHasCompletedCall(Entity $opportunity): bool
    {
        $orConditions = [
            [
                'parentType' => 'Opportunity',
                'parentId' => $opportunity->getId(),
            ],
        ];

        $leadId = $opportunity->get('leadId');

        if ($leadId) {
            $orConditions[] = [
                'parentType' => 'Lead',
                'parentId' => $leadId,
            ];
        }

        $prospectId = $opportunity->get('prospectId');

        if ($prospectId) {
            $orConditions[] = [
                'prospectId' => $prospectId,
            ];
        }

        $call = $this->entityManager
            ->getRDBRepository('Call')
            ->where([
                'status' => ['Held', 'Not Held'],
                'OR' => $orConditions,
            ])
            ->findOne();

        return $call !== null;
    }

    private function countPhoneContactsTodo(): int
    {
        return (int) $this->entityManager
            ->getRDBRepository('Call')
            ->where([
                'status' => 'Planned',
            ])
            ->count();
    }

    private function countContractsBacklog(): int
    {
        return (int) $this->entityManager
            ->getRDBRepository('Opportunity')
            ->where([
                'stage' => 'Closed Won',
                'OR' => [
                    ['statoContratto' => 'Sospeso'],
                    [
                        'AND' => [
                            ['finanziamento' => true],
                            ['statoFinanziamento' => self::FINANCING_BACKLOG_STATES],
                        ],
                    ],
                ],
            ])
            ->count();
    }

    private function countContractsInProgress(): int
    {
        return (int) $this->entityManager
            ->getRDBRepository('Opportunity')
            ->where([
                'stage' => 'Closed Won',
                'statoContratto' => 'In lavorazione',
            ])
            ->count();
    }

    /**
     * @return array{count: int, provvigioniMeta: string}
     */
    private function countContractsInPayment(?string $from, ?string $to): array
    {
        $where = array_merge(
            [
                'stage' => 'Closed Won',
                'statoContratto' => 'Installato',
            ],
            $this->dateWhere('installazione', $from, $to)
        );

        $collection = $this->entityManager
            ->getRDBRepository('Opportunity')
            ->select(['id'])
            ->where($where)
            ->find();

        $opportunityIds = [];

        foreach ($collection as $opportunity) {
            $opportunityIds[] = $opportunity->getId();
        }

        $count = count($opportunityIds);
        $provvigioniAmount = $this->sumProvvigioniForOpportunities($opportunityIds);

        return [
            'count' => $count,
            'provvigioniMeta' => $this->formatProvvigioniMeta($provvigioniAmount),
        ];
    }

    /**
     * @param string[] $opportunityIds
     */
    private function sumProvvigioniForOpportunities(array $opportunityIds): float
    {
        if ($opportunityIds === []) {
            return 0.0;
        }

        foreach (['importoConsolidato', 'importoPrevisto'] as $attribute) {
            try {
                $sum = $this->entityManager
                    ->getRDBRepository('Provvigione')
                    ->where([
                        'opportunitaId' => $opportunityIds,
                        'statoProvvigione!=' => 'Stornata',
                    ])
                    ->sum($attribute);

                if ($sum !== null) {
                    return (float) $sum;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return 0.0;
    }

    private function formatProvvigioniMeta(float $amount): string
    {
        if ($amount <= 0.0) {
            return '0 € provvigioni';
        }

        return number_format(round($amount, 0), 0, ',', '.') . ' € provvigioni';
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
}
