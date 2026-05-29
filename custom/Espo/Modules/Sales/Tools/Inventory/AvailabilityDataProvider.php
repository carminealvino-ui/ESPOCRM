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

namespace Espo\Modules\Sales\Tools\Inventory;

use Espo\Modules\Sales\Entities\DeliveryOrder;
use Espo\Modules\Sales\Entities\InventoryTransaction;
use Espo\Modules\Sales\Entities\Product;
use Espo\Modules\Sales\Entities\SalesOrder;
use Espo\Modules\Sales\Entities\TransferOrder;
use Espo\Modules\Sales\Entities\Warehouse;
use Espo\Modules\Sales\Tools\Inventory\Availability\Params;
use Espo\Modules\Sales\Tools\Inventory\Availability\ProductData;
use Espo\Modules\Sales\Tools\Inventory\Availability\WarehouseData;
use Espo\Modules\Sales\Tools\Sales\ConfigDataProvider;
use Espo\ORM\Collection;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Query\Part\Condition;
use Espo\ORM\Query\Part\Expression as Expr;
use Espo\ORM\Query\Select;
use Espo\ORM\Query\SelectBuilder;
use RuntimeException;

/**
 * No ACL check.
 */
class AvailabilityDataProvider
{
    public function __construct(
        private EntityManager $entityManager,
        private ConfigDataProvider $configDataProvider
    ) {}

    /**
     * @param string[] $ids Product IDs.
     * @return ProductData[]
     */
    public function getForProducts(array $ids, ?string $excludeType = null, ?string $excludeId = null): array
    {
        /** @var Collection<Warehouse> $warehouses */
        $warehouses = $this->entityManager
            ->getRDBRepository(Warehouse::ENTITY_TYPE)
            ->where(['status' => Warehouse::STATUS_ACTIVE])
            ->order('name')
            ->find();

        $order = null;

        if ($excludeId && $excludeType) {
            $order = $this->entityManager->getEntityById($excludeType, $excludeId);
        }

        return array_map(function ($id) use ($warehouses, $order) {
            return $this->getForProduct(
                id: $id,
                warehouses: $warehouses,
                order: $order,
            );
        }, $ids);
    }

    /**
     * @return WarehouseData[]
     */
    public function getWarehousesForProduct(Product $product, Params $params): array
    {
        $builder = SelectBuilder::create()
            ->from(InventoryTransaction::ENTITY_TYPE)
            ->select('warehouseId')
            ->select(
                Expr::sum(Expr::column('quantity')),
                'sum'
            )
            ->where(
                Condition::in(
                    Expr::column('warehouseId'),
                    SelectBuilder::create()
                        ->from(Warehouse::ENTITY_TYPE)
                        ->select('id')
                        ->where(['status' => Warehouse::STATUS_ACTIVE])
                        ->build()
                )
            );

        if ($params->stocked) {
            $builder->having(
                Expr::greaterOrEqual(
                    Expr::sum(Expr::column('quantity')),
                    0.0
                )
            );
        }

        if ($product->getType() === Product::TYPE_TEMPLATE) {
            $builder
                ->join(Product::ENTITY_TYPE, 'product', ['product.id:' => 'productId'])
                ->group('product.templateId')
                ->where(['product.templateId' => $product->getId()]);
        } else {
            $builder
                ->group('productId')
                ->where(['productId' => $product->getId()]);
        }

        $query = $builder
            ->group('warehouseId')
            ->build();

        /** @var array<string, array{quantity: float, reserved?: float, softReserved?: float}> $map */
        $map = [];

        $query1 = SelectBuilder::create()
            ->clone($query)
            ->build();

        $sth = $this->entityManager->getQueryExecutor()->execute($query1);

        $warehouseIds = [];

        while ($row = $sth->fetch()) {
            $warehouseId = $row['warehouseId'];

            if (!in_array($warehouseId, $warehouseIds)) {
                $warehouseIds[] = $warehouseId;
            }

            $map[$warehouseId] = [
                'quantity' => (float) $row['sum'],
            ];
        }

        $query2 = SelectBuilder::create()
            ->clone($query)
            ->where(['type' => InventoryTransaction::TYPE_RESERVE])
            ->build();

        $sth = $this->entityManager->getQueryExecutor()->execute($query2);

        while ($row = $sth->fetch()) {
            $warehouseId = $row['warehouseId'];
            $sum = (float) $row['sum'];

            if (!in_array($warehouseId, $warehouseIds)) {
                $warehouseIds[] = $warehouseId;
            }

            $map[$warehouseId] ??= [];
            $map[$warehouseId]['reserved'] = $sum ? -1.0 * $sum : 0.0;
        }

        $query3 = SelectBuilder::create()
            ->clone($query)
            ->where(['type' => InventoryTransaction::TYPE_SOFT_RESERVE])
            ->build();

        $sth = $this->entityManager->getQueryExecutor()->execute($query3);

        while ($row = $sth->fetch()) {
            $warehouseId = $row['warehouseId'];
            $sum = (float) $row['sum'];

            if (!in_array($warehouseId, $warehouseIds)) {
                $warehouseIds[] = $warehouseId;
            }

            $map[$warehouseId] ??= [];
            $map[$warehouseId]['softReserved'] = $sum ? -1.0 * $sum : 0.0;
        }

        $where = [];

        if ($params->stocked) {
            $where['id'] = $warehouseIds;
        }

        $warehouses = $this->entityManager
            ->getRDBRepositoryByClass(Warehouse::class)
            ->where(['status' => Warehouse::STATUS_ACTIVE])
            ->order('name')
            ->where($where)
            ->find();

        $dataList = [];

        foreach ($warehouses as $warehouse) {
            $warehouseId = $warehouse->getId() ;

            $quantity = $map[$warehouseId]['quantity'] ?? 0.0;
            $quantityReserved = $map[$warehouseId]['reserved'] ?? 0.0;
            $quantitySoftReserved = $map[$warehouseId]['softReserved'] ?? 0.0;

            $dataList[] = new WarehouseData(
                id: $warehouse->getId(),
                quantity: $quantity,
                name: $warehouse->getName(),
                quantityReserved: $quantityReserved,
                quantitySoftReserved: $quantitySoftReserved,
            );
        }

        return $dataList;
    }

    /**
     * @param string $id An inventory number ID.
     * @return WarehouseData[]
     */
    public function getWarehousesForNumber(string $id, ?Params $params = null): array
    {
        $params ??= new Params(stocked: false);

        $builder = SelectBuilder::create()
            ->from(InventoryTransaction::ENTITY_TYPE)
            ->select('warehouseId')
            ->select(
                Expr::sum(Expr::column('quantity')),
                'sum'
            )
            ->where(['inventoryNumberId' => $id])
            ->where(
                Condition::in(
                    Expr::column('warehouseId'),
                    SelectBuilder::create()
                        ->from(Warehouse::ENTITY_TYPE)
                        ->select('id')
                        ->where(['status' => Warehouse::STATUS_ACTIVE])
                        ->build()
                )
            )
            ->group('warehouseId')
            ->group('inventoryNumberId');

        if ($params->stocked) {
            $builder->having(
                Expr::greaterOrEqual(
                    Expr::sum(Expr::column('quantity')),
                    0.0
                )
            );
        }

        $query = $builder->build();

        /** @var array<string, array{onHand: float, reserved?: float}> $map */
        $map = [];

        $query1 = SelectBuilder::create()
            ->clone($query)
            ->build();

        $sth = $this->entityManager->getQueryExecutor()->execute($query1);

        $warehouseIds = [];

        while ($row = $sth->fetch()) {
            $warehouseId = $row['warehouseId'];

            if (!in_array($warehouseId, $warehouseIds)) {
                $warehouseIds[] = $warehouseId;
            }

            $map[$warehouseId] = [
                'onHand' => (float) $row['sum'],
            ];
        }

        $query2 = SelectBuilder::create()
            ->clone($query)
            ->where(['type' => InventoryTransaction::TYPE_RESERVE])
            ->build();

        $sth = $this->entityManager->getQueryExecutor()->execute($query2);

        while ($row = $sth->fetch()) {
            $warehouseId = $row['warehouseId'];
            $sum = (float) $row['sum'];

            if (!in_array($warehouseId, $warehouseIds)) {
                $warehouseIds[] = $warehouseId;
            }

            $map[$warehouseId] ??= [];
            $map[$warehouseId]['reserved'] = $sum ? -1.0 * $sum : 0.0;
        }

        $where = [];

        if ($params->stocked) {
            $where['id'] = $warehouseIds;
        }

        $warehouses = $this->entityManager
            ->getRDBRepositoryByClass(Warehouse::class)
            ->where(['status' => Warehouse::STATUS_ACTIVE])
            ->order('name')
            ->where($where)
            ->find();

        $dataList = [];

        foreach ($warehouses as $warehouse) {
            $warehouseId = $warehouse->getId() ;

            $quantity = $map[$warehouseId]['onHand'] ?? 0.0;
            $quantityReserved = $map[$warehouseId]['reserved'] ?? 0.0;
            $quantitySoftReserved = $map[$warehouseId]['softReserved'] ?? 0.0;

            $dataList[] = new WarehouseData(
                id: $warehouse->getId(),
                quantity: $quantity,
                name: $warehouse->getName(),
                quantityReserved: $quantityReserved,
                quantitySoftReserved: $quantitySoftReserved,
                quantityOnHand: $quantity - $quantitySoftReserved,
            );
        }

        return $dataList;
    }

    /**
     * @return ProductData[]
     */
    public function getForTransferOrder(TransferOrder $order): array
    {
        $warehouseId = $order->getFromWarehouse()->getId();

        /** @var ?Warehouse $warehouse */
        $warehouse = $this->entityManager
            ->getRDBRepository(Warehouse::ENTITY_TYPE)
            ->getById($warehouseId);

        if (!$warehouse) {
            throw new RuntimeException("No warehouse $warehouseId.");
        }

        $warehouses = [$warehouse];

        $pairs = $order->getInventoryPairs();

        return array_map(function ($pair) use ($warehouses, $order) {
            return $this->getForProduct(
                $pair->getProductId(),
                $warehouses,
                $order,
                true,
                $pair->getNumberId()
            );
        }, $pairs);
    }

    /**
     * @return ProductData[]
     */
    public function getForDeliveryOrder(DeliveryOrder $order): array
    {
        $warehouses = [];

        if ($order->getWarehouse()) {
            /** @var ?Warehouse $warehouse */
            $warehouse = $this->entityManager
                ->getRDBRepository(Warehouse::ENTITY_TYPE)
                ->getById($order->getWarehouse()->getId());

            if ($warehouse) {
                $warehouses[] = $warehouse;
            }
        }

        $pairs = $order->getInventoryPairs();

        if ($order->isNew() && $order->getSalesOrder()) {
            $order = $this->entityManager
                ->getEntityById(SalesOrder::ENTITY_TYPE, $order->getSalesOrder()->getId());
        }

        return array_map(function ($pair) use ($warehouses, $order) {
            return $this->getForProduct(
                $pair->getProductId(),
                $warehouses,
                $order,
                true,
                $pair->getNumberId()
            );
        }, $pairs);
    }

    /**
     * @param iterable<Warehouse> $warehouses
     */
    private function getForProduct(
        string $id,
        iterable $warehouses,
        ?Entity $order = null,
        bool $asPairs = false,
        ?string $inventoryNumberId = null,
    ): ProductData {

        $queryBuilder = SelectBuilder::create()
            ->from(InventoryTransaction::ENTITY_TYPE)
            ->select(
                Expr::sum(
                    Expr::column('quantity')
                ),
                'sum'
            )
            ->where(['productId' => $id])
            ->group('productId');

        if ($asPairs) {
            $queryBuilder
                ->where(['inventoryNumberId' => $inventoryNumberId])
                ->group('inventoryNumberId');
        }

        if ($order && $order->hasId()) {
            $queryBuilder->where([
                'OR' => [
                    'parentType!=' => $order->getEntityType(),
                    'parentId!=' => $order->getId(),
                    'parentId' => null,
                ]
            ]);
        }

        if ($asPairs) {
            $queryBuilder->where(['type!=' => InventoryTransaction::TYPE_SOFT_RESERVE]);
        }

        $query = $queryBuilder->build();

        $quantity = $this->fetchQuantity($query);

        $onHand = null;
        $queryOnHand = null;

        if (!$asPairs) {
            $queryOnHand = SelectBuilder::create()
                ->clone($query)
                ->where(['type!=' => InventoryTransaction::TYPE_SOFT_RESERVE])
                ->build();

            $onHand = $this->fetchQuantity($queryOnHand);
        }

        /** @var ?Product $product */
        $product = $this->entityManager
            ->getRDBRepository(Product::ENTITY_TYPE)
            ->select(['id', 'name'])
            ->where(['id' => $id])
            ->findOne();

        if (!$product) {
            throw new RuntimeException("Product $id not found.");
        }

        $productDataId = $asPairs ?
            $id . '_' . ($inventoryNumberId ?? '') :
            $id;

        $data = new ProductData(
            id: $productDataId,
            quantity: $quantity,
            name: $product->getName(),
            productId: $id,
            inventoryNumberId: $inventoryNumberId,
            quantityOnHand: $onHand,
        );

        if (!$this->configDataProvider->isWarehousesEnabled()) {
            return $data;
        }

        $map = $this->fetchWarehouseQuantityMap($query);

        $onHandMap = $queryOnHand ?
            $this->fetchWarehouseQuantityMap($queryOnHand) : null;

        foreach ($warehouses as $warehouse) {
            $quantity = $map[$warehouse->getId()] ?? 0.0;

            if (!($order instanceof TransferOrder)) {
                $quantity = min($quantity, $data->getQuantity());
            }

            $onHand = null;

            if ($onHandMap !== null) {
                $onHand = $onHandMap[$warehouse->getId()] ?? 0.0;
            }

            $data = $data->withWarehouseAdded(
                id: $warehouse->getId(),
                quantity: $quantity,
                name: $warehouse->getName(),
                quantityOnHand: $onHand,
            );
        }

        return $data;
    }

    private function fetchQuantity(Select $query): float
    {
        $sth = $this->entityManager->getQueryExecutor()->execute($query);

        $quantity = 0.0;

        if ($row = $sth->fetch()) {
            $quantity = (float) $row['sum'];
        }

        return $quantity;
    }

    /**
     * @return array<string, float>
     */
    private function fetchWarehouseQuantityMap(Select $query): array
    {
        $query = SelectBuilder::create()
            ->clone($query)
            ->select('warehouseId')
            ->group('warehouseId')
            ->where(['warehouseId!=' => null])
            ->build();

        $sth = $this->entityManager->getQueryExecutor()->execute($query);

        $map = [];

        while ($row = $sth->fetch()) {
            /** @var string $warehouseId */
            $warehouseId = $row['warehouseId'];

            $map[$warehouseId] = (float) $row['sum'];
        }

        return $map;
    }
}
