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

namespace Espo\Modules\Sales\Tools\Inventory\Archive;

use Espo\Core\Field\LinkParent;
use Espo\Modules\Sales\Entities\InventoryTransaction;
use Espo\ORM\Collection;
use Espo\ORM\EntityManager;
use Espo\ORM\Query\DeleteBuilder;
use Espo\ORM\Query\Part\Expression as Expr;
use Espo\ORM\Query\Select;
use Espo\ORM\Query\SelectBuilder;

/**
 * Removes balanced transactions.
 */
class Compressor
{
    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function run(?Params $params = null): Result
    {
        $params ??= new Params();

        $query = $this->buildGroupQuery($params);

        $counter = new Counter();

        $sth = $this->entityManager
            ->getQueryExecutor()
            ->execute($query);

        while ($row = $sth->fetch()) {
            $this->processGroup(
                params: $params,
                counter: $counter,
                productId: $row['productId'],
                inventoryNumberId: $row['inventoryNumberId'],
                warehouseId: $row['warehouseId'],
                type: $row['type'],
                parentType: $row['parentType'],
                parentId: $row['parentId']
            );
        }

        return new Result($counter->getValue());
    }

    /**
     * @param InventoryTransaction::TYPE_* $type
     */
    private function processGroup(
        Params $params,
        Counter $counter,
        string $productId,
        ?string $inventoryNumberId,
        ?string $warehouseId,
        string $type,
        ?string $parentType,
        ?string $parentId
    ): void {

        $this->entityManager
            ->getTransactionManager()
            ->run(
                fn () => $this->processGroupTransaction(
                    params: $params,
                    counter: $counter,
                    productId: $productId,
                    inventoryNumberId: $inventoryNumberId,
                    warehouseId: $warehouseId,
                    type: $type,
                    parentType: $parentType,
                    parentId: $parentId
                )
            );
    }

    /**
     * @param InventoryTransaction::TYPE_* $type
     */
    private function processGroupTransaction(
        Params $params,
        Counter $counter,
        string $productId,
        ?string $inventoryNumberId,
        ?string $warehouseId,
        string $type,
        ?string $parentType,
        ?string $parentId
    ): void {

        $where = [
            'productId' => $productId,
            'inventoryNumberId' => $inventoryNumberId,
            'warehouseId' => $warehouseId,
            'type' => $type,
            'parentType' => $parentType,
            'parentId' => $parentId,
        ];

        $query = SelectBuilder::create()
            ->forUpdate()
            ->select('id')
            ->from(InventoryTransaction::ENTITY_TYPE)
            ->where($where)
            ->build();

        $transactions = $this->entityManager
            ->getRDBRepositoryByClass(InventoryTransaction::class)
            ->clone($query)
            ->find();

        $groupQuery = SelectBuilder::create()
            ->clone($this->buildGroupQuery($params))
            ->where($where)
            ->build();

        $row = $this->entityManager
            ->getQueryExecutor()
            ->execute($groupQuery)
            ->fetch();

        if (!$row) {
            return;
        }

        $quantity = (float) $row['sum'];
        $count = count(iterator_to_array($transactions));

        $this->delete($transactions);

        $counter->add($count);

        if ($quantity === 0.0) {
            return;
        }

        $counter->add(-1);

        $this->create(
            productId: $productId,
            type: $type,
            inventoryNumberId: $inventoryNumberId,
            warehouseId: $warehouseId,
            parentType: $parentType,
            parentId: $parentId,
            quantity: $quantity
        );
    }

    public function buildGroupQuery(Params $params): Select
    {
        $orderExpr = Expr::max(Expr::column('number'));

        $moreThanOneExpr =
            Expr::greater(
                Expr::count(Expr::column('id')),
                1
            );

        $builder = SelectBuilder::create()
            ->from(InventoryTransaction::ENTITY_TYPE)
            ->select([
                'productId',
                'inventoryNumberId',
                'warehouseId',
                'type',
                'parentType',
                'parentId',
            ])
            ->select($moreThanOneExpr, 'count')
            ->select($orderExpr, 'maxNumber')
            ->select(
                Expr::sum(Expr::column('quantity')),
                'sum'
            )
            ->group([
                'productId',
                'inventoryNumberId',
                'warehouseId',
                'type',
                'parentType',
                'parentId',
            ])
            ->having($moreThanOneExpr)
            ->order($orderExpr);

        if ($params->getBefore()) {
            $createdAtExpr = Expr::max(Expr::column('createdAt'));

            $beforeExpr = Expr::less(
                $createdAtExpr,
                $params->getBefore()->getString()
            );

            $builder
                ->select($createdAtExpr)
                ->having($beforeExpr);
        }

        return $builder->build();
    }

    /**
     * @param Collection<InventoryTransaction> $transactions
     */
    public function delete(Collection $transactions): void
    {
        $ids = [];

        foreach ($transactions as $transaction) {
            $ids[] = $transaction->getId();
        }

        $deleteQuery = DeleteBuilder::create()
            ->from(InventoryTransaction::ENTITY_TYPE)
            ->where(['id' => $ids])
            ->build();

        $this->entityManager
            ->getQueryExecutor()
            ->execute($deleteQuery);
    }

    /**
     * @param InventoryTransaction::TYPE_* $type
     */
    private function create(
        string $productId,
        string $type,
        ?string $inventoryNumberId,
        ?string $warehouseId,
        ?string $parentType,
        ?string $parentId,
        float $quantity
    ): void {

        $newNumber = $this->entityManager
            ->getRDBRepositoryByClass(InventoryTransaction::class)
            ->getNew();

        $newNumber
            ->setType($type)
            ->setProductId($productId)
            ->setInventoryNumberId($inventoryNumberId)
            ->setWarehouseId($warehouseId)
            ->setQuantity($quantity);

        if ($parentType && $parentId) {
            $newNumber->setParent(LinkParent::create($parentType, $parentId));
        }

        $this->entityManager->saveEntity($newNumber);
    }
}
