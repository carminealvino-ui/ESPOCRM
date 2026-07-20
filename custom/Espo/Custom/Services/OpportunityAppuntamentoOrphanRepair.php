<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Ripara Opportunity.appuntamento quando l'ID punta a un Appuntamento
 * mancante (in scheda compare l'ID grezzo, es. 67ebb599b5324ca2c).
 *
 * Strategia:
 * 1) se esiste un altro Appuntamento per lo stesso Lead/Prospect → ricollega
 * 2) altrimenti azzera appuntamentoId/appuntamentoName
 */
class OpportunityAppuntamentoOrphanRepair
{
    public function __construct(
        private EntityManager $entityManager
    ) {}

    /**
     * @return array{needs: bool, reason: ?string}
     */
    public function diagnose(Entity $opportunity): array
    {
        $appuntamentoId = trim((string) $opportunity->get('appuntamentoId'));
        $appuntamentoName = trim((string) $opportunity->get('appuntamentoName'));

        if ($appuntamentoId === '') {
            if ($appuntamentoName !== '') {
                return ['needs' => true, 'reason' => 'name-without-id'];
            }

            return ['needs' => false, 'reason' => null];
        }

        $appuntamento = $this->entityManager->getEntityById('Appuntamento', $appuntamentoId);

        if (!$appuntamento) {
            return ['needs' => true, 'reason' => 'orphan-id'];
        }

        if ($appuntamentoName === '' || $appuntamentoName === $appuntamentoId) {
            return ['needs' => true, 'reason' => 'empty-or-id-as-name'];
        }

        $liveName = trim((string) $appuntamento->get('name'));

        if ($liveName !== '' && $liveName !== $appuntamentoName) {
            return ['needs' => true, 'reason' => 'stale-name'];
        }

        return ['needs' => false, 'reason' => null];
    }

    /**
     * Solo set in memoria (hook beforeSave / bonifica dry-run).
     *
     * @return array{
     *   changed: bool,
     *   beforeId: ?string,
     *   beforeName: ?string,
     *   afterId: ?string,
     *   afterName: ?string,
     *   reason: ?string
     * }
     */
    public function apply(Entity $opportunity): array
    {
        $beforeId = $opportunity->get('appuntamentoId');
        $beforeName = $opportunity->get('appuntamentoName');
        $diag = $this->diagnose($opportunity);

        if (!$diag['needs']) {
            return [
                'changed' => false,
                'beforeId' => $beforeId,
                'beforeName' => $beforeName,
                'afterId' => $beforeId,
                'afterName' => $beforeName,
                'reason' => null,
            ];
        }

        $resolved = $this->resolveReplacement($opportunity);

        if ($resolved !== null) {
            $opportunity->set('appuntamentoId', $resolved['id']);
            $opportunity->set('appuntamentoName', $resolved['name']);

            return [
                'changed' => true,
                'beforeId' => $beforeId,
                'beforeName' => $beforeName,
                'afterId' => $resolved['id'],
                'afterName' => $resolved['name'],
                'reason' => 'relinked',
            ];
        }

        // Nessun appuntamento valido: opportunità senza appuntamento correlato.
        if ($beforeId || $beforeName) {
            $opportunity->set('appuntamentoId', null);
            $opportunity->set('appuntamentoName', null);

            return [
                'changed' => true,
                'beforeId' => $beforeId,
                'beforeName' => $beforeName,
                'afterId' => null,
                'afterName' => null,
                'reason' => 'cleared-orphan',
            ];
        }

        return [
            'changed' => false,
            'beforeId' => $beforeId,
            'beforeName' => $beforeName,
            'afterId' => $beforeId,
            'afterName' => $beforeName,
            'reason' => null,
        ];
    }

    /**
     * @return array{
     *   changed: bool,
     *   beforeId: ?string,
     *   beforeName: ?string,
     *   afterId: ?string,
     *   afterName: ?string,
     *   reason: ?string
     * }
     */
    public function repair(Entity $opportunity, bool $dryRun = false): array
    {
        $result = $this->apply($opportunity);

        if ($result['changed'] && !$dryRun) {
            $this->entityManager->saveEntity($opportunity, [
                'skipHooks' => true,
                'silent' => true,
            ]);
        }

        return $result;
    }

    /**
     * @return array{id: string, name: string}|null
     */
    public function resolveReplacement(Entity $opportunity): ?array
    {
        $currentId = trim((string) $opportunity->get('appuntamentoId'));

        if ($currentId !== '') {
            $current = $this->entityManager->getEntityById('Appuntamento', $currentId);

            if ($current) {
                $name = trim((string) $current->get('name'));

                return [
                    'id' => $current->getId(),
                    'name' => $name !== '' ? $name : $current->getId(),
                ];
            }
        }

        $replacement = $this->findAppuntamentoForOpportunity($opportunity);

        if (!$replacement) {
            return null;
        }

        // Non ricollegare allo stesso ID orfano.
        if ($currentId !== '' && $replacement->getId() === $currentId) {
            return null;
        }

        $name = trim((string) $replacement->get('name'));

        return [
            'id' => $replacement->getId(),
            'name' => $name !== '' ? $name : $replacement->getId(),
        ];
    }

    private function findAppuntamentoForOpportunity(Entity $opportunity): ?Entity
    {
        $leadId = $opportunity->get('leadId');
        $prospectId = $opportunity->get('prospectId');

        if ($prospectId) {
            $byProspect = $this->entityManager
                ->getRDBRepository('Appuntamento')
                ->where(['prospectId' => $prospectId])
                ->order('dateStart', 'DESC')
                ->findOne();

            if ($byProspect) {
                return $byProspect;
            }
        }

        if (!$leadId) {
            return null;
        }

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
            $byBoth = $this->entityManager
                ->getRDBRepository('Appuntamento')
                ->where(array_merge($leadWhere, ['prospectId' => $prospectId]))
                ->order('dateStart', 'DESC')
                ->findOne();

            if ($byBoth) {
                return $byBoth;
            }
        }

        return $this->entityManager
            ->getRDBRepository('Appuntamento')
            ->where($leadWhere)
            ->order('dateStart', 'DESC')
            ->findOne();
    }
}
