<?php

namespace Espo\Custom\Tools\CrmKpi;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class Alerts
{
    /** @var string[] */
    private const ESITI_ANNULLATI = [
        'Annullato dal Potenziale',
        'Annullato dal Consulente',
        'Annullato Azienda',
        'Annullato Call Center',
        'Appuntamento non in agenda',
    ];

    /** @var string[] */
    private const FINANCING_SUSPENDED_STATES = [
        'In rivalutazione',
        'In Attesa Documentazione',
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
    public function build(?string $from, ?string $to, ?string $productBrandId = null): object
    {
        unset($from, $to);

        return (object) [
            'criticita' => $this->buildCriticita($productBrandId),
        ];
    }

    /**
     * Criticità operative per entità (valori totali, non filtrati per periodo KPI).
     *
     * @return object[]
     */
    private function buildCriticita(?string $productBrandId): array
    {
        return [
            $this->alert(
                'appuntamentiSenzaOpportunita',
                'Senza opportunità',
                $this->countAppuntamentiSenzaOpportunita($productBrandId),
                '#Appuntamento/list/primaryFilter=appuntamentiSenzaOpportunita',
                null,
                'criticita',
                'appuntamenti'
            ),
            $this->alert(
                'appuntamentiConPiuOpportunita',
                'Con più opportunità',
                $this->countAppuntamentiConPiuOpportunita($productBrandId),
                '#Appuntamento/list/primaryFilter=appuntamentiConPiuOpportunita',
                null,
                'criticita',
                'appuntamenti'
            ),
            $this->alert(
                'opportunityWithoutWhatsapp',
                'Senza invio WhatsApp',
                $this->countOpportunitiesWithoutWhatsapp($productBrandId),
                '#Opportunity/list/primaryFilter=senzaInvioWhatsapp',
                null,
                'criticita',
                'opportunita'
            ),
            $this->alert(
                'opportunityWithoutPhoneFollowUp',
                'Senza riscontro telefonico',
                $this->countOpportunitiesWithoutPhoneFollowUp($productBrandId),
                '#Opportunity/list/primaryFilter=senzaRiscontroTelefonico',
                null,
                'criticita',
                'opportunita'
            ),
            $this->alert(
                'contractsSuspendedFinancing',
                'Sospesi finanziamento',
                $this->countContractsSuspendedFinancing($productBrandId),
                '#Quote/list/primaryFilter=contrattiSospesiFinanziamento',
                null,
                'criticita',
                'contratti'
            ),
            $this->alert(
                'contractsSuspendedOrders',
                'Sospesi ordini',
                $this->countContractsSuspendedOrders($productBrandId),
                '#Quote/list/primaryFilter=contrattiSospesiOrdini',
                null,
                'criticita',
                'contratti'
            ),
            $this->alert(
                'richiamiPianificati',
                'Richiami pianificati',
                $this->countRichiamiPianificati(),
                '#Call/list/primaryFilter=richiamiPianificati',
                null,
                'criticita',
                'chiamate'
            ),
            $this->alert(
                'chiamateScadute',
                'Chiamate scadute',
                $this->countChiamateScadute(),
                '#Call/list/primaryFilter=chiamateScadute',
                null,
                'criticita',
                'chiamate'
            ),
        ];
    }

    private function alert(
        string $key,
        string $label,
        int $value,
        string $link,
        ?string $meta = null,
        string $group = 'criticita',
        string $entity = 'appuntamenti'
    ): object {
        $item = (object) [
            'key' => $key,
            'label' => $label,
            'value' => $value,
            'link' => $link,
            'group' => $group,
            'entity' => $entity,
        ];

        if ($meta !== null && $meta !== '') {
            $item->meta = $meta;
        }

        return $item;
    }

    private function countAppuntamentiSenzaOpportunita(?string $productBrandId = null): int
    {
        $heldIds = $this->getHeldAppuntamentoIds($productBrandId);

        if ($heldIds === []) {
            return 0;
        }

        $withOpportunity = $this->mapAppuntamentoOpportunityCounts($heldIds);

        $count = 0;

        foreach ($heldIds as $id) {
            if (($withOpportunity[$id] ?? 0) === 0) {
                $count++;
            }
        }

        return $count;
    }

    private function countAppuntamentiConPiuOpportunita(?string $productBrandId = null): int
    {
        $heldIds = $this->getHeldAppuntamentoIds($productBrandId);

        if ($heldIds === []) {
            return 0;
        }

        $counts = $this->mapAppuntamentoOpportunityCounts($heldIds);

        $count = 0;

        foreach ($counts as $value) {
            if ($value > 1) {
                $count++;
            }
        }

        return $count;
    }

    private function countOpportunitiesWithoutWhatsapp(?string $productBrandId = null): int
    {
        $where = [
            'AND' => [
                ['stage!=' => 'Closed Won'],
                ['stage!=' => 'Closed Lost'],
                ['appuntamentoId!=' => null],
                ['appuntamentoId!=' => ''],
            ],
        ];

        if ($productBrandId) {
            $where['productBrandId'] = $productBrandId;
        }

        $count = 0;

        $collection = $this->entityManager
            ->getRDBRepository('Opportunity')
            ->select(['id', 'leadId', 'prospectId'])
            ->where($where)
            ->find();

        foreach ($collection as $opportunity) {
            if (!$this->opportunityHasWhatsappCall($opportunity)) {
                $count++;
            }
        }

        return $count;
    }

    private function countOpportunitiesWithoutPhoneFollowUp(?string $productBrandId = null): int
    {
        $where = [
            'AND' => [
                ['stage!=' => 'Closed Won'],
                ['stage!=' => 'Closed Lost'],
            ],
        ];

        if ($productBrandId) {
            $where['productBrandId'] = $productBrandId;
        }

        $count = 0;

        $collection = $this->entityManager
            ->getRDBRepository('Opportunity')
            ->select(['id', 'leadId', 'prospectId', 'appuntamentoId'])
            ->where($where)
            ->find();

        foreach ($collection as $opportunity) {
            if (!$this->opportunityHasCompletedCall($opportunity)) {
                $count++;
            }
        }

        return $count;
    }

    private function countRichiamiPianificati(): int
    {
        return (int) $this->entityManager
            ->getRDBRepository('Call')
            ->where([
                'status' => 'Planned',
                'AND' => [
                    ['richiamo!=' => ''],
                    ['richiamo!=' => null],
                ],
            ])
            ->count();
    }

    private function countChiamateScadute(): int
    {
        return (int) $this->entityManager
            ->getRDBRepository('Call')
            ->where([
                'status' => 'Planned',
                'dateStart<' => date('Y-m-d H:i:s'),
            ])
            ->count();
    }

    private function countContractsSuspendedFinancing(?string $productBrandId = null): int
    {
        $where = [
            'statoContratto!=' => self::CLOSED_CONTRACT_STATES,
            'finanziamento' => true,
            'statoFinanziamento' => self::FINANCING_SUSPENDED_STATES,
        ];

        if ($productBrandId) {
            $where['productBrandId'] = $productBrandId;
        }

        return (int) $this->entityManager
            ->getRDBRepository('Quote')
            ->where($where)
            ->count();
    }

    private function countContractsSuspendedOrders(?string $productBrandId = null): int
    {
        $where = [
            'statoContratto' => 'Sospeso',
        ];

        if ($productBrandId) {
            $where['productBrandId'] = $productBrandId;
        }

        return (int) $this->entityManager
            ->getRDBRepository('Quote')
            ->where($where)
            ->count();
    }

    /**
     * @return string[]
     */
    private function getHeldAppuntamentoIds(?string $productBrandId = null): array
    {
        $ids = [];

        $where = [
            'status' => 'Held',
        ];

        if ($productBrandId) {
            $where['productBrandId'] = $productBrandId;
        }

        $collection = $this->entityManager
            ->getRDBRepository('Appuntamento')
            ->select(['id', 'esito', 'sottostato'])
            ->where($where)
            ->find();

        foreach ($collection as $appuntamento) {
            $sottostato = (string) ($appuntamento->get('sottostato') ?? '');

            // Gestito / Rifissato: fuori dal monitoraggio.
            if (in_array($sottostato, ['Gestito', 'Rifissato'], true)) {
                continue;
            }

            if ($this->isAppuntamentoNotAnnullato($appuntamento)) {
                $ids[] = $appuntamento->getId();
            }
        }

        return $ids;
    }

    /**
     * @param string[] $appuntamentoIds
     * @return array<string, int>
     */
    private function mapAppuntamentoOpportunityCounts(array $appuntamentoIds): array
    {
        $counts = [];

        foreach (array_chunk($appuntamentoIds, 500) as $chunk) {
            $collection = $this->entityManager
                ->getRDBRepository('Opportunity')
                ->select(['appuntamentoId'])
                ->where(['appuntamentoId' => $chunk])
                ->find();

            foreach ($collection as $opportunity) {
                $appuntamentoId = $opportunity->get('appuntamentoId');

                if (!$appuntamentoId) {
                    continue;
                }

                $counts[$appuntamentoId] = ($counts[$appuntamentoId] ?? 0) + 1;
            }
        }

        return $counts;
    }

    private function opportunityHasWhatsappCall(Entity $opportunity): bool
    {
        $call = $this->entityManager
            ->getRDBRepository('Call')
            ->where([
                'AND' => [
                    ['OR' => $this->buildOpportunityCallLinkConditions($opportunity)],
                    [
                        'OR' => [
                            ['tipologia*' => 'WhatsApp%'],
                            ['whatsApp' => true],
                        ],
                    ],
                ],
            ])
            ->findOne();

        return $call !== null;
    }

    private function opportunityHasCompletedCall(Entity $opportunity): bool
    {
        $call = $this->entityManager
            ->getRDBRepository('Call')
            ->where([
                'status' => ['Held', 'Not Held'],
                'OR' => $this->buildOpportunityCallLinkConditions($opportunity),
            ])
            ->findOne();

        return $call !== null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildOpportunityCallLinkConditions(Entity $opportunity): array
    {
        $conditions = [
            [
                'parentType' => 'Opportunity',
                'parentId' => $opportunity->getId(),
            ],
        ];

        $leadId = $opportunity->get('leadId');

        if ($leadId) {
            $conditions[] = [
                'parentType' => 'Lead',
                'parentId' => $leadId,
            ];
        }

        $prospectId = $opportunity->get('prospectId');

        if ($prospectId) {
            $conditions[] = [
                'prospectId' => $prospectId,
            ];
        }

        $appuntamentoId = $opportunity->get('appuntamentoId');

        if ($appuntamentoId) {
            $conditions[] = [
                'nota*' => '%Auto-Pending-Appuntamento: ' . $appuntamentoId . '%',
            ];
        }

        return $conditions;
    }

    private function isAppuntamentoNotAnnullato(Entity $appuntamento): bool
    {
        if ($appuntamento->get('sottostato') === 'Annullato') {
            return false;
        }

        $esito = $appuntamento->get('esito');

        return !($esito && in_array($esito, self::ESITI_ANNULLATI, true));
    }
}
