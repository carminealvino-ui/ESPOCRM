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
    public function build(?string $from, ?string $to, ?string $productBrandId = null): array
    {
        return [
            $this->alert(
                'appuntamentiSenzaOpportunita',
                'Appuntamenti senza opportunità',
                $this->countAppuntamentiSenzaOpportunita($from, $to, $productBrandId),
                '#Appuntamento/filter/appuntamentiSenzaOpportunita',
                null,
                'avvisi'
            ),
            $this->alert(
                'appuntamentiConPiuOpportunita',
                'Appuntamenti con più opportunità',
                $this->countAppuntamentiConPiuOpportunita($from, $to, $productBrandId),
                '#Appuntamento/filter/appuntamentiConPiuOpportunita',
                null,
                'avvisi'
            ),
            $this->alert(
                'opportunityWithoutWhatsapp',
                'Opportunità senza invio WhatsApp',
                $this->countOpportunitiesWithoutWhatsapp($from, $to, $productBrandId),
                '#Opportunity/filter/senzaInvioWhatsapp',
                null,
                'avvisi'
            ),
            $this->alert(
                'opportunityWithoutPhoneFollowUp',
                'Opportunità senza Riscontro',
                $this->countOpportunitiesWithoutPhoneFollowUp($from, $to, $productBrandId),
                '#Opportunity/filter/senzaRiscontroPeriodo',
                null,
                'avvisi'
            ),
            $this->alert(
                'richiamiPianificati',
                'Richiami Pianificati',
                $this->countRichiamiPianificati(),
                '#Call/filter/richiamiPianificati',
                null,
                'avvisi'
            ),
            $this->alert(
                'contractsSuspendedFinancing',
                'Contratti Sospesi Finanziamento',
                $this->countContractsSuspendedFinancing($productBrandId),
                '#Quote/filter/contrattiSospesiFinanziamento',
                null,
                'criticita'
            ),
            $this->alert(
                'contractsSuspendedOrders',
                'Contratti Sospesi Ordini',
                $this->countContractsSuspendedOrders($productBrandId),
                '#Quote/filter/contrattiInLavorazione',
                null,
                'criticita'
            ),
        ];
    }

    private function alert(
        string $key,
        string $label,
        int $value,
        string $link,
        ?string $meta = null,
        string $group = 'avvisi'
    ): object {
        $item = (object) [
            'key' => $key,
            'label' => $label,
            'value' => $value,
            'link' => $link,
            'group' => $group,
        ];

        if ($meta !== null && $meta !== '') {
            $item->meta = $meta;
        }

        return $item;
    }

    private function countAppuntamentiSenzaOpportunita(
        ?string $from,
        ?string $to,
        ?string $productBrandId = null
    ): int {
        $heldIds = $this->getHeldAppuntamentoIdsInPeriod($from, $to, $productBrandId);

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

    private function countAppuntamentiConPiuOpportunita(
        ?string $from,
        ?string $to,
        ?string $productBrandId = null
    ): int {
        $heldIds = $this->getHeldAppuntamentoIdsInPeriod($from, $to, $productBrandId);

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

    private function countOpportunitiesWithoutWhatsapp(
        ?string $from,
        ?string $to,
        ?string $productBrandId = null
    ): int {
        $appuntamentoIds = $this->getAppuntamentoIdsInPeriod($from, $to, $productBrandId);

        if ($appuntamentoIds === []) {
            return 0;
        }

        $where = [
            'AND' => [
                ['stage!=' => 'Closed Won'],
                ['stage!=' => 'Closed Lost'],
                ['appuntamentoId' => $appuntamentoIds],
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

    private function countOpportunitiesWithoutPhoneFollowUp(
        ?string $from,
        ?string $to,
        ?string $productBrandId = null
    ): int {
        $appuntamentoIds = $this->getAppuntamentoIdsInPeriod($from, $to, $productBrandId);

        if ($appuntamentoIds === []) {
            return 0;
        }

        $where = [
            'AND' => [
                ['stage!=' => 'Closed Won'],
                ['stage!=' => 'Closed Lost'],
                ['appuntamentoId' => $appuntamentoIds],
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
            'statoContratto' => 'In lavorazione',
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
    private function getHeldAppuntamentoIdsInPeriod(
        ?string $from,
        ?string $to,
        ?string $productBrandId = null
    ): array {
        $ids = [];

        $where = array_merge(
            [
                'status' => 'Held',
                'sottostato!=' => self::ESITI_ANNULLATI,
            ],
            $this->dateWhere('dataAppuntamento', $from, $to)
        );

        if ($productBrandId) {
            $where['productBrandId'] = $productBrandId;
        }

        $collection = $this->entityManager
            ->getRDBRepository('Appuntamento')
            ->select(['id', 'esito'])
            ->where($where)
            ->find();

        foreach ($collection as $appuntamento) {
            if ($this->isAppuntamentoNotAnnullato($appuntamento)) {
                $ids[] = $appuntamento->getId();
            }
        }

        return $ids;
    }

    /**
     * @return string[]
     */
    private function getAppuntamentoIdsInPeriod(
        ?string $from,
        ?string $to,
        ?string $productBrandId = null
    ): array {
        $ids = [];

        $where = $this->dateWhere('dataAppuntamento', $from, $to);

        if ($productBrandId) {
            $where['productBrandId'] = $productBrandId;
        }

        $collection = $this->entityManager
            ->getRDBRepository('Appuntamento')
            ->select(['id'])
            ->where($where)
            ->find();

        foreach ($collection as $appuntamento) {
            $ids[] = $appuntamento->getId();
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
        $sottostato = $appuntamento->get('sottostato');

        if ($sottostato && in_array($sottostato, self::ESITI_ANNULLATI, true)) {
            return false;
        }

        $esito = $appuntamento->get('esito');

        return !($esito && in_array($esito, self::ESITI_ANNULLATI, true));
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
