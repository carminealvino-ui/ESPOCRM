<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Query\SelectBuilder;

/**
 * Ripara Opportunity.appuntamento quando l'ID punta a un Appuntamento
 * apparentemente mancante (in scheda compare l'ID grezzo).
 *
 * Strategia:
 * 1) se l'Appuntamento esiste ma è soft-deleted (deleted=1) → ripristina
 * 2) se esiste un altro Appuntamento per Lead/Prospect → ricollega
 * 3) altrimenti azzera appuntamentoId/appuntamentoName
 */
class OpportunityAppuntamentoOrphanRepair
{
    public function __construct(
        private EntityManager $entityManager
    ) {}

    /**
     * Carica Appuntamento anche se soft-deleted.
     */
    public function findIncludingDeleted(string $id): ?Entity
    {
        $id = trim($id);

        if ($id === '') {
            return null;
        }

        $active = $this->entityManager->getEntityById('Appuntamento', $id);

        if ($active) {
            return $active;
        }

        $query = SelectBuilder::create()
            ->from('Appuntamento')
            ->withDeleted()
            ->build();

        return $this->entityManager
            ->getRDBRepository('Appuntamento')
            ->clone($query)
            ->where(['id' => $id])
            ->findOne();
    }

    /**
     * @return array{exists: bool, deleted: bool, id: ?string, name: ?string, dateStart: ?string, status: string}
     */
    public function inspectAppuntamento(string $id): array
    {
        $row = $this->findIncludingDeleted($id);

        if (!$row) {
            return [
                'exists' => false,
                'deleted' => false,
                'id' => $id,
                'name' => null,
                'dateStart' => null,
                'status' => 'missing',
            ];
        }

        $deleted = (bool) $row->get('deleted');

        return [
            'exists' => true,
            'deleted' => $deleted,
            'id' => $row->getId(),
            'name' => $row->get('name'),
            'dateStart' => $row->get('dateStart'),
            'status' => $deleted ? 'soft-deleted' : 'active',
        ];
    }

    /**
     * Ripristina Appuntamento soft-deleted (deleted → 0).
     *
     * @return array{restored: bool, id: string, name: ?string, alreadyActive: bool}
     */
    public function restoreAppuntamento(string $id, bool $dryRun = false): array
    {
        $entity = $this->findIncludingDeleted($id);

        if (!$entity) {
            return [
                'restored' => false,
                'id' => $id,
                'name' => null,
                'alreadyActive' => false,
            ];
        }

        if (!(bool) $entity->get('deleted')) {
            return [
                'restored' => false,
                'id' => $entity->getId(),
                'name' => $entity->get('name'),
                'alreadyActive' => true,
            ];
        }

        if (!$dryRun) {
            // Espo soft-delete: UPDATE diretto + clear cache entity.
            $pdo = $this->entityManager->getPDO();
            $stmt = $pdo->prepare(
                'UPDATE `appuntamento` SET `deleted` = 0 WHERE `id` = :id'
            );
            $stmt->bindValue(':id', $id);
            $stmt->execute();

            // Invalida eventuale cache ORM.
            if (method_exists($this->entityManager, 'getEntityManager')) {
                // no-op
            }

            $entity = $this->entityManager->getEntityById('Appuntamento', $id);

            if (!$entity) {
                // Fallback: set su entity caricata con withDeleted.
                $entity = $this->findIncludingDeleted($id);

                if ($entity) {
                    $entity->set('deleted', false);
                    $this->entityManager->saveEntity($entity, [
                        'skipHooks' => true,
                        'silent' => true,
                    ]);
                    $entity = $this->entityManager->getEntityById('Appuntamento', $id) ?: $entity;
                }
            }
        }

        return [
            'restored' => true,
            'id' => $entity->getId(),
            'name' => $entity->get('name'),
            'alreadyActive' => false,
        ];
    }

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

        if ($appuntamento) {
            if ($appuntamentoName === '' || $appuntamentoName === $appuntamentoId) {
                return ['needs' => true, 'reason' => 'empty-or-id-as-name'];
            }

            $liveName = trim((string) $appuntamento->get('name'));

            if ($liveName !== '' && $liveName !== $appuntamentoName) {
                return ['needs' => true, 'reason' => 'stale-name'];
            }

            return ['needs' => false, 'reason' => null];
        }

        $includingDeleted = $this->findIncludingDeleted($appuntamentoId);

        if ($includingDeleted && (bool) $includingDeleted->get('deleted')) {
            return ['needs' => true, 'reason' => 'soft-deleted'];
        }

        return ['needs' => true, 'reason' => 'orphan-id'];
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

        $currentId = trim((string) $beforeId);

        // 1) Soft-delete: ripristina e risincronizza nome.
        if ($diag['reason'] === 'soft-deleted' && $currentId !== '') {
            $restored = $this->restoreAppuntamento($currentId, false);
            $entity = $this->entityManager->getEntityById('Appuntamento', $currentId);

            if ($restored['restored'] || $restored['alreadyActive']) {
                $name = trim((string) (
                    ($entity ? $entity->get('name') : null)
                    ?: $restored['name']
                    ?: ''
                ));

                $opportunity->set('appuntamentoId', $currentId);
                $opportunity->set('appuntamentoName', $name !== '' ? $name : $currentId);

                return [
                    'changed' => true,
                    'beforeId' => $beforeId,
                    'beforeName' => $beforeName,
                    'afterId' => $currentId,
                    'afterName' => $opportunity->get('appuntamentoName'),
                    'reason' => 'restored-soft-deleted',
                ];
            }
        }

        $resolved = $this->resolveReplacement($opportunity);

        if ($resolved !== null) {
            $opportunity->set('appuntamentoId', $resolved['id']);
            $opportunity->set('appuntamentoName', $resolved['name']);

            $reason = ($diag['reason'] === 'empty-or-id-as-name' || $diag['reason'] === 'stale-name')
                ? 'synced-name'
                : 'relinked';

            return [
                'changed' => true,
                'beforeId' => $beforeId,
                'beforeName' => $beforeName,
                'afterId' => $resolved['id'],
                'afterName' => $resolved['name'],
                'reason' => $reason,
            ];
        }

        // Nessun appuntamento recuperabile: azzera solo se davvero missing.
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
        if ($dryRun) {
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

            if ($diag['reason'] === 'soft-deleted' && $beforeId) {
                $info = $this->inspectAppuntamento((string) $beforeId);

                return [
                    'changed' => true,
                    'beforeId' => $beforeId,
                    'beforeName' => $beforeName,
                    'afterId' => $beforeId,
                    'afterName' => $info['name'] ?: $beforeId,
                    'reason' => 'restored-soft-deleted',
                ];
            }

            // Simula senza scrivere.
            $cloneId = $beforeId;
            $cloneName = $beforeName;
            $resolved = $this->resolveReplacement($opportunity);

            if ($resolved) {
                return [
                    'changed' => true,
                    'beforeId' => $beforeId,
                    'beforeName' => $beforeName,
                    'afterId' => $resolved['id'],
                    'afterName' => $resolved['name'],
                    'reason' => 'relinked',
                ];
            }

            return [
                'changed' => true,
                'beforeId' => $cloneId,
                'beforeName' => $cloneName,
                'afterId' => null,
                'afterName' => null,
                'reason' => 'cleared-orphan',
            ];
        }

        $result = $this->apply($opportunity);

        if ($result['changed']) {
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
