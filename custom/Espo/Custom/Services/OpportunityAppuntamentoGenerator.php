<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Collega o crea Appuntamento per Opportunità senza appuntamento_id.
 *
 * Flusso:
 * 1) Se esiste già un Appuntamento per Lead/Prospect → collega
 * 2) Altrimenti crea Appuntamento storico dai campi Opportunità
 * 3) Aggiorna opportunity.appuntamentoId
 */
class OpportunityAppuntamentoGenerator
{
    private const DURATION_SECONDS = 5400;

    private const DEFAULT_TIME = '09:00:00';

    /** @var string[] */
    private const COPY_FIELDS = [
        'prospectId',
        'prospectName',
        'leadId',
        'leadName',
        'fornitorePartnerId',
        'fornitorePartnerName',
        'productBrandId',
        'productBrandName',
        'productCategoryId',
        'productCategoryName',
        'cAPId',
        'cAPName',
        'assignedUserId',
        'assignedUserName',
        'azienda',
    ];

    public function __construct(
        private EntityManager $entityManager,
    ) {}

    /**
     * @return array{
     *     action: 'linked'|'created'|'skipped',
     *     reason: string,
     *     appuntamentoId: ?string,
     *     appuntamentoName: ?string
     * }
     */
    public function process(Entity $opportunity, bool $dryRun = false, bool $linkOnly = false): array
    {
        if ($opportunity->get('appuntamentoId')) {
            $existing = $this->entityManager->getEntityById(
                'Appuntamento',
                (string) $opportunity->get('appuntamentoId')
            );

            if ($existing) {
                return $this->result('skipped', 'Già collegato', (string) $existing->getId(), $existing->get('name'));
            }
        }

        $leadId = $opportunity->get('leadId');
        $prospectId = $opportunity->get('prospectId');

        if (!$leadId && !$prospectId) {
            return $this->result('skipped', 'Manca Lead e Prospect');
        }

        $appuntamento = $this->resolveExistingAppuntamento($opportunity);

        if ($appuntamento) {
            return $this->linkOpportunity($opportunity, $appuntamento, $dryRun, 'Collegato appuntamento esistente');
        }

        if ($linkOnly) {
            return $this->result('skipped', 'Nessun appuntamento esistente (link-only)');
        }

        $duplicate = $this->findDuplicateSlot($opportunity);

        if ($duplicate) {
            return $this->linkOpportunity($opportunity, $duplicate, $dryRun, 'Collegato duplicato nello slot');
        }

        return $this->createAndLink($opportunity, $dryRun);
    }

    private function resolveExistingAppuntamento(Entity $opportunity): ?Entity
    {
        $leadId = $opportunity->get('leadId');
        $prospectId = $opportunity->get('prospectId');

        if ($leadId) {
            $leadWhere = [
                'OR' => [
                    ['leadId' => $leadId],
                    [
                        'parentType' => 'Lead',
                        'parentId' => $leadId,
                    ],
                ],
            ];

            if ($prospectId) {
                $byProspect = $this->entityManager
                    ->getRDBRepository('Appuntamento')
                    ->where(array_merge($leadWhere, ['prospectId' => $prospectId]))
                    ->order('dateStart', 'DESC')
                    ->findOne();

                if ($byProspect) {
                    return $byProspect;
                }
            }

            $byLead = $this->entityManager
                ->getRDBRepository('Appuntamento')
                ->where($leadWhere)
                ->order('dateStart', 'DESC')
                ->findOne();

            if ($byLead) {
                return $byLead;
            }
        }

        if ($prospectId) {
            return $this->entityManager
                ->getRDBRepository('Appuntamento')
                ->where(['prospectId' => $prospectId])
                ->order('dateStart', 'DESC')
                ->findOne();
        }

        return null;
    }

    private function findDuplicateSlot(Entity $opportunity): ?Entity
    {
        [$dateStart, $dateEnd] = $this->resolveDateRange($opportunity);

        if (!$dateStart || !$dateEnd) {
            return null;
        }

        $identityWhere = $this->buildIdentityWhere($opportunity);

        if (!$identityWhere) {
            return null;
        }

        return $this->entityManager
            ->getRDBRepository('Appuntamento')
            ->where(array_merge([
                'dateStart' => $dateStart,
                'dateEnd' => $dateEnd,
            ], $identityWhere))
            ->findOne();
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveDateRange(Entity $opportunity): array
    {
        $date = $this->resolveReferenceDate($opportunity);

        if (!$date) {
            return [null, null];
        }

        $dateStart = $date . ' ' . self::DEFAULT_TIME;

        try {
            $dateTime = new \DateTime($dateStart);
        } catch (\Throwable) {
            return [null, null];
        }

        $dateTime->modify('+' . self::DURATION_SECONDS . ' seconds');

        return [$dateStart, $dateTime->format('Y-m-d H:i:s')];
    }

    private function resolveReferenceDate(Entity $opportunity): ?string
    {
        $dataOpportunit = $opportunity->get('dataOpportunit');

        if (is_string($dataOpportunit) && preg_match('/^\d{4}-\d{2}-\d{2}/', $dataOpportunit)) {
            return substr($dataOpportunit, 0, 10);
        }

        $closeDate = $opportunity->get('closeDate');

        if (is_string($closeDate) && preg_match('/^\d{4}-\d{2}-\d{2}/', $closeDate)) {
            return substr($closeDate, 0, 10);
        }

        $name = (string) $opportunity->get('name');

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $name, $matches)) {
            return $matches[1];
        }

        $createdAt = $opportunity->get('createdAt');

        if (is_string($createdAt) && preg_match('/^\d{4}-\d{2}-\d{2}/', $createdAt)) {
            return substr($createdAt, 0, 10);
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildIdentityWhere(Entity $opportunity): ?array
    {
        if ($opportunity->get('prospectId')) {
            return ['prospectId' => $opportunity->get('prospectId')];
        }

        if ($opportunity->get('leadId')) {
            return [
                'OR' => [
                    ['leadId' => $opportunity->get('leadId')],
                    [
                        'parentType' => 'Lead',
                        'parentId' => $opportunity->get('leadId'),
                    ],
                ],
            ];
        }

        return null;
    }

    /**
     * @return array{
     *     action: 'linked'|'created'|'skipped',
     *     reason: string,
     *     appuntamentoId: ?string,
     *     appuntamentoName: ?string
     * }
     */
    private function createAndLink(Entity $opportunity, bool $dryRun): array
    {
        [$dateStart, $dateEnd] = $this->resolveDateRange($opportunity);

        if (!$dateStart || !$dateEnd) {
            return $this->result('skipped', 'Data appuntamento non risolvibile');
        }

        $appuntamento = $this->entityManager->getNewEntity('Appuntamento');

        foreach (self::COPY_FIELDS as $field) {
            $value = $opportunity->get($field);

            if ($value !== null && $value !== '') {
                $appuntamento->set($field, $value);
            }
        }

        $this->applyParentFromOpportunity($opportunity, $appuntamento);
        $this->applyAddressFromSource($opportunity, $appuntamento);
        $this->applyStatusFromStage($opportunity, $appuntamento);

        $description = trim((string) ($opportunity->get('description') ?: ''));
        $referenceDate = $this->resolveReferenceDate($opportunity);

        $fields = [
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
            'description' => $description !== ''
                ? $description
                : 'Appuntamento generato da opportunità (bonifica storica)',
        ];

        if ($referenceDate && $appuntamento->hasAttribute('dataAppuntamento')) {
            $fields['dataAppuntamento'] = $referenceDate;
        }

        $appuntamento->set($fields);

        if ($dryRun) {
            $nameBuilder = new AppuntamentoNameBuilder($this->entityManager);
            $appuntamento->set('name', $nameBuilder->build($appuntamento));

            return $this->result(
                'created',
                'Creazione simulata',
                '(dry-run)',
                $appuntamento->get('name')
            );
        }

        $this->entityManager->saveEntity($appuntamento, [
            'skipHooks' => true,
            'silent' => true,
            'skipFormula' => true,
        ]);

        $nameBuilder = new AppuntamentoNameBuilder($this->entityManager);

        if ($nameBuilder->needsRebuild($appuntamento)) {
            $nameBuilder->rebuild($appuntamento);
        }

        return $this->linkOpportunity(
            $opportunity,
            $appuntamento,
            false,
            'Appuntamento creato e collegato'
        );
    }

    private function applyParentFromOpportunity(Entity $opportunity, Entity $appuntamento): void
    {
        if ($appuntamento->get('prospectId')) {
            $appuntamento->set([
                'parentType' => 'Prospect',
                'parentId' => $appuntamento->get('prospectId'),
                'parentName' => $appuntamento->get('prospectName'),
            ]);

            return;
        }

        if ($appuntamento->get('leadId')) {
            $appuntamento->set([
                'parentType' => 'Lead',
                'parentId' => $appuntamento->get('leadId'),
                'parentName' => $appuntamento->get('leadName'),
            ]);
        }
    }

    private function applyAddressFromSource(Entity $opportunity, Entity $appuntamento): void
    {
        $source = null;

        if ($opportunity->get('prospectId')) {
            $source = $this->entityManager->getEntityById('Prospect', $opportunity->get('prospectId'));
        } elseif ($opportunity->get('leadId')) {
            $source = $this->entityManager->getEntityById('Lead', $opportunity->get('leadId'));
        }

        if (!$source) {
            return;
        }

        $addressMap = [
            'indirizzoStreet' => 'addressStreet',
            'indirizzoCity' => 'addressCity',
            'indirizzoPostalCode' => 'addressPostalCode',
            'indirizzoState' => 'addressState',
            'indirizzoCountry' => 'addressCountry',
        ];

        foreach ($addressMap as $target => $sourceField) {
            $value = $source->get($sourceField);

            if ($value !== null && $value !== '') {
                $appuntamento->set($target, $value);
            }
        }

        if (!$appuntamento->get('cAPId') && $source->get('cAPId')) {
            $appuntamento->set('cAPId', $source->get('cAPId'));
            $appuntamento->set('cAPName', $source->get('cAPName'));
        }
    }

    private function applyStatusFromStage(Entity $opportunity, Entity $appuntamento): void
    {
        $stage = (string) ($opportunity->get('stage') ?: '');

        $status = match ($stage) {
            'Closed Won' => ['status' => 'Held', 'sottostato' => 'Chiuso Positivamente'],
            'Closed Lost' => ['status' => 'Held', 'sottostato' => 'Non Interessato'],
            default => ['status' => 'Planned', 'sottostato' => null],
        };

        $appuntamento->set('status', $status['status']);
        $appuntamento->set('sottostato', $status['sottostato']);
    }

    /**
     * @return array{
     *     action: 'linked'|'created'|'skipped',
     *     reason: string,
     *     appuntamentoId: ?string,
     *     appuntamentoName: ?string
     * }
     */
    private function linkOpportunity(
        Entity $opportunity,
        Entity $appuntamento,
        bool $dryRun,
        string $reason
    ): array {
        $appuntamentoId = (string) $appuntamento->getId();
        $appuntamentoName = (string) ($appuntamento->get('name') ?: '');

        if ($dryRun) {
            return $this->result('linked', $reason . ' (dry-run)', $appuntamentoId, $appuntamentoName);
        }

        $opportunity->set([
            'appuntamentoId' => $appuntamentoId,
            'appuntamentoName' => $appuntamentoName,
        ]);

        $this->entityManager->saveEntity($opportunity, [
            'skipHooks' => true,
            'silent' => true,
            'skipFormula' => true,
        ]);

        $action = str_contains($reason, 'creato') ? 'created' : 'linked';

        return $this->result($action, $reason, $appuntamentoId, $appuntamentoName);
    }

    /**
     * @return array{
     *     action: 'linked'|'created'|'skipped',
     *     reason: string,
     *     appuntamentoId: ?string,
     *     appuntamentoName: ?string
     * }
     */
    private function result(
        string $action,
        string $reason,
        ?string $appuntamentoId = null,
        ?string $appuntamentoName = null
    ): array {
        return [
            'action' => $action,
            'reason' => $reason,
            'appuntamentoId' => $appuntamentoId,
            'appuntamentoName' => $appuntamentoName,
        ];
    }
}
