<?php
/***********************************************************************************
 * The contents of this file are subject to the Extension License Agreement
 * ("Agreement") which can be viewed at
 * https://www.espocrm.com/extension-license-agreement/.
 * By copying, installing downloading, or using this file, You have unconditionally
 * agreed to the terms and conditions of the Agreement, and You may not use this
 * file except in compliance with the Agreement. Under the terms of the Agreement,
 * You shall not license, sublicense, sell, resell, rent, lease, lend, distribute,
 * redistribute, market, publish, commercialize, or otherwise transfer rights or
 * usage to the software or any modified version or derivative work of the software
 * created by or for you.
 *
 * Copyright (C) 2015-2026 EspoCRM, Inc.
 *
 * License ID: 11af5a568c1a72dce4e164257d1a0207
 ************************************************************************************/

namespace Espo\Modules\Sales\Tools\Warehouse;

use Espo\Core\Acl;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Record\Collection;
use Espo\Core\Record\Collection as RecordCollection;
use Espo\Core\Record\ServiceContainer;
use Espo\Core\Select\SearchParams;
use Espo\Core\Select\SelectBuilderFactory;
use Espo\Modules\Sales\Entities\InventoryNumber;
use Espo\Modules\Sales\Entities\InventoryTransaction;
use Espo\Modules\Sales\Entities\Warehouse;
use Espo\ORM\EntityManager;
use Espo\ORM\Query\Part\Condition as Cond;
use Espo\ORM\Query\Part\Expression as Expr;
use Espo\ORM\Query\Part\Join;
use Espo\ORM\Query\SelectBuilder;

class InventoryNumbersService
{
    public function __construct(
        private EntityManager $entityManager,
        private Acl $acl,
        private SelectBuilderFactory $selectBuilderFactory,
        private ServiceContainer $serviceContainer
    ) {}

    /**
     * @return Collection<InventoryNumber>
     * @throws Forbidden
     * @throws NotFound
     * @throws BadRequest
     */
    public function find(string $id, SearchParams $searchParams): Collection
    {
        $warehouse = $this->getWarehouse($id);

        $queryBuilder = $this->selectBuilderFactory
            ->create()
            ->from(InventoryNumber::ENTITY_TYPE)
            ->withStrictAccessControl()
            ->withSearchParams($searchParams)
            ->buildQueryBuilder();

        $this->applyWarehouseToQuery($queryBuilder, $warehouse);

        $query = $queryBuilder->build();

        $repository = $this->entityManager->getRDBRepositoryByClass(InventoryNumber::class);

        $collection = $repository->clone($query)->find();
        $total = $repository->clone($query)->count();

        $service = $this->serviceContainer->getByClass(InventoryNumber::class);

        foreach ($collection as $entity) {
            $service->prepareEntityForOutput($entity);
        }

        return RecordCollection::create($collection, $total);
    }

    /**
     * @param string $id
     * @throws Forbidden
     * @throws NotFound
     */
    private function getWarehouse(string $id): Warehouse
    {
        if (!$this->acl->checkScope(Warehouse::ENTITY_TYPE)) {
            throw new Forbidden("No access to Warehouse scope.");
        }

        if (!$this->acl->checkScope(InventoryNumber::ENTITY_TYPE)) {
            throw new Forbidden("No access to InventoryNumber scope.");
        }

        $warehouse = $this->entityManager
            ->getRDBRepositoryByClass(Warehouse::class)
            ->getById($id);

        if (!$warehouse) {
            throw new NotFound("Warehouse $id does not exist.");
        }

        if (!$this->acl->checkEntityRead($warehouse)) {
            throw new Forbidden("No access to warehouse $id.");
        }

        return $warehouse;
    }

    private function applyWarehouseToQuery(SelectBuilder $queryBuilder, Warehouse $warehouse): void
    {
        $subQueryOnHand = $this->getSubQueryBuilder($warehouse)
            ->where(['type!=' => InventoryTransaction::TYPE_SOFT_RESERVE])
            ->build();

        $subQueryReserved = $this->getSubQueryBuilder($warehouse, true)
            ->where(['type' => InventoryTransaction::TYPE_RESERVE])
            ->build();

        $selectOnHandExpr =
            Expr::coalesce(
                Expr::column('warehouseOnHandSq.sum'),
                Expr::value('0.0')
            );

        $subQueryReservedExpr =
            Expr::coalesce(
                Expr::column('warehouseReservedSq.sum'),
                Expr::value('0.0')
            );

        $queryBuilder
            ->select($selectOnHandExpr, 'quantityWarehouseOnHand')
            ->select($subQueryReservedExpr, 'quantityWarehouseReserved')
            ->leftJoin(
                Join::createWithSubQuery($subQueryOnHand, 'warehouseOnHandSq')
                    ->withConditions(
                        Cond::equal(
                            Expr::column('warehouseOnHandSq.nid'),
                            Expr::column('id')
                        )
                    )
            )
            ->leftJoin(
                Join::createWithSubQuery($subQueryReserved, 'warehouseReservedSq')
                    ->withConditions(
                        Cond::equal(
                            Expr::column('warehouseReservedSq.nid'),
                            Expr::column('id')
                        )
                    )
            )
            ->where(
                Cond::or(
                    Cond::greater($selectOnHandExpr, 0.0),
                    Cond::greater($subQueryReservedExpr, 0.0),
                )
            );
    }

    private function getSubQueryBuilder(Warehouse $warehouse, bool $negate = false): SelectBuilder
    {
        $sumExpression = Expr::sum(Expr::column('quantity'));

        if ($negate) {
            $sumExpression = Expr::multiply($sumExpression, -1.0);
        }

        return SelectBuilder::create()
            ->from(InventoryTransaction::ENTITY_TYPE)
            ->select($sumExpression, 'sum')
            // 'nid' is used to avoid conversion to underscore.
            ->select('inventoryNumberId', 'nid')
            ->group('inventoryNumberId')
            ->where(['warehouseId' => $warehouse->getId()]);
    }
}
