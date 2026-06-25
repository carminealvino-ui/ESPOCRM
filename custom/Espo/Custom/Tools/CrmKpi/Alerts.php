<?php

namespace Espo\Custom\Tools\CrmKpi;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class Alerts
{
    /** @var string[] */
    private const FINANCING_BACKLOG_STATES = [
        'In rivalutazione',
        'In Attesa Documentazione',
        'Respinto',
    ];

    /** @var string[] */
    private const CLOSED_CONTRACT_STATES = [
        'Annullato',
        'Recesso',
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
                $this->countOpportunitiesWithoutPhoneFollowUp($from, $to),
                '#Opportunity/filter/senzaRiscontroPeriodo'
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
                '#Quote/filter/contrattiBacklog'
            ),
            $this->alert(
                'contractsInProgress',
                'Contratti in lavorazione',
                $this->countContractsInProgress(),
                '#Quote/filter/contrattiInLavorazione'
            ),
            $this->alert(
                'contractsInPayment',
                'Contratti in pagamento (data installazione nel periodo)',
                $contractsInPayment['count'],
                '#Quote/filter/dataInstallazionePeriodo',
                $contractsInPayment['meta']
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

    private function countOpportunitiesWithoutPhoneFollowUp(?string $from, ?string $to): int
    {
        $appuntamentoIds = $this->getAppuntamentoIdsInPeriod($from, $to);

        if ($appuntamentoIds === []) {
            return 0;
        }

        $count = 0;

        $collection = $this->entityManager
            ->getRDBRepository('Opportunity')
            ->select(['id', 'leadId', 'prospectId', 'appuntamentoId'])
            ->where([
                'AND' => [
                    ['stage!=' => 'Closed Won'],
                    ['stage!=' => 'Closed Lost'],
                    ['appuntamentoId' => $appuntamentoIds],
                ],
            ])
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

        $appuntamentoId = $opportunity->get('appuntamentoId');

        if ($appuntamentoId) {
            $orConditions[] = [
                'nota*' => '%Auto-Pending-Appuntamento: ' . $appuntamentoId . '%',
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
            ->getRDBRepository('Quote')
            ->where([
                'statoContratto!=' => self::CLOSED_CONTRACT_STATES,
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
            ->getRDBRepository('Quote')
            ->where([
                'statoContratto' => 'In lavorazione',
            ])
            ->count();
    }

    /**
     * @return array{count: int, meta: string}
     */
    private function countContractsInPayment(?string $from, ?string $to): array
    {
        $where = array_merge(
            [
                'AND' => [
                    ['dataInstallazione!=' => null],
                    ['dataInstallazione!=' => ''],
                ],
            ],
            $this->dateWhere('dataInstallazione', $from, $to)
        );

        $count = (int) $this->entityManager
            ->getRDBRepository('Quote')
            ->where($where)
            ->count();

        $amount = $this->safeSum('Quote', $where, [
            'importoContratto',
            'amount',
            'grandTotalAmount',
        ]);

        $provvigioni = $this->safeSum('Quote', $where, [
            'totaleProvvigioni',
        ]);

        return [
            'count' => $count,
            'meta' => $this->formatPaymentMeta($amount, $provvigioni),
        ];
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

    private function formatPaymentMeta(float $amount, float $provvigioni): string
    {
        return $this->formatCurrency($amount) . ' importo · ' . $this->formatCurrency($provvigioni) . ' provvigioni';
    }

    private function formatCurrency(float $amount): string
    {
        return number_format(round($amount, 0), 0, ',', '.') . ' €';
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
