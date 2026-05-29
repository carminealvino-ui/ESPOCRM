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

use Espo\Core\Field\LinkParent;
use Espo\Modules\Sales\Entities\DeliveryOrder;
use Espo\Modules\Sales\Entities\DeliveryOrderItem;
use Espo\Modules\Sales\Entities\InventoryAdjustment;
use Espo\Modules\Sales\Entities\InventoryAdjustmentItem;
use Espo\Modules\Sales\Entities\InventoryTransaction;
use Espo\Modules\Sales\Entities\PurchaseOrder;
use Espo\Modules\Sales\Entities\QuoteItem;
use Espo\Modules\Sales\Entities\ReceiptOrder;
use Espo\Modules\Sales\Entities\ReceiptOrderItem;
use Espo\Modules\Sales\Entities\ReceiptOrderReceivedItem;
use Espo\Modules\Sales\Entities\SalesOrder;
use Espo\Modules\Sales\Entities\TransferOrder;
use Espo\Modules\Sales\Entities\TransferOrderItem;
use Espo\Modules\Sales\Tools\Sales\ConfigDataProvider;
use Espo\ORM\Collection;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Query\Part\Expression as Expr;

class TransactionManager
{
    public function __construct(
        private EntityManager $entityManager,
        private ConfigDataProvider $configDataProvider
    ) {}

    public function adjustOut(
        SalesOrder|DeliveryOrder|TransferOrder $order,
        ?string $type = null
    ): void {

        $this->adjustInternal($order, $type, true);
    }

    public function adjustIn(
        PurchaseOrder|ReceiptOrder|TransferOrder|InventoryAdjustment $order,
        ?string $type = null
    ): void {

        $this->adjustInternal($order, $type, false);
    }

    private function adjustInternal(
        SalesOrder|PurchaseOrder|DeliveryOrder|ReceiptOrder|TransferOrder|InventoryAdjustment $order,
        ?string $type,
        bool $isOut
    ): void {

        $warehouseId = null;

        if ($order instanceof TransferOrder) {
            $warehouseId = $isOut ?
                $order->getFromWarehouse()->getId() :
                $order->getToWarehouse()->getId();
        }

        $groups = $this->getGroups($order, $warehouseId);

        if (!$type) {
            $this->negate($groups, $order);

            return;
        }

        $this->negate($groups, $order, $type);

        $items = $this->getItems($order, $type);

        foreach ($groups as $group) {
            /** @var InventoryTransaction $group */

            if ($type !== $group->getType()) {
                continue;
            }

            $this->adjustGroupItem($order, $isOut, $group, $items);
        }

        foreach ($items as $item) {
            if (!$item->getProduct()) {
                continue;
            }

            $quantity = $this->getItemQuantity($item, $isOut);

            if (!$quantity) {
                continue;
            }

            $inventoryNumberId =
                (
                    $item instanceof TransferOrderItem ||
                    $item instanceof DeliveryOrderItem ||
                    $item instanceof ReceiptOrderReceivedItem ||
                    $item instanceof InventoryAdjustmentItem
                ) ?
                    $item->getInventoryNumber()?->getId() :
                    null;

            foreach ($groups as $group) {
                if (
                    $group->getProduct()->getId() === $item->getProduct()->getId() &&
                    $group->getType() === $type &&
                    $group->getInventoryNumber()?->getId() === $inventoryNumberId
                ) {
                    continue 2;
                }
            }

            if ($isOut) {
                $quantity = - $quantity;
            }

            /** @var InventoryTransaction::TYPE_* $type */

            $this->createTransaction(
                $order,
                $item->getProduct()->getId(),
                $quantity,
                $type,
                $inventoryNumberId,
                $warehouseId ?? $this->getWarehouseId($order)
            );
        }
    }

    private function getWarehouseId(
        SalesOrder|PurchaseOrder|DeliveryOrder|ReceiptOrder|TransferOrder|InventoryAdjustment $order
    ): ?string {

        if (
            $order instanceof DeliveryOrder ||
            $order instanceof ReceiptOrder ||
            $order instanceof PurchaseOrder ||
            $order instanceof InventoryAdjustment
        ) {
            return $order->getWarehouse()?->getId();
        }

        return $order->get('warehouseId');
    }

    /**
     * @param (QuoteItem|ReceiptOrderReceivedItem)[] $items
     */
    private function adjustGroupItem(
        SalesOrder|PurchaseOrder|DeliveryOrder|ReceiptOrder|TransferOrder|InventoryAdjustment $order,
        bool $isOut,
        InventoryTransaction $group,
        array $items
    ): void {

        $quantity = 0.0;

        foreach ($items as $item) {
            if (!$item->getProduct()) {
                continue;
            }

            if ($item->getProduct()->getId() !== $group->getProduct()->getId()) {
                continue;
            }

            if (
                (
                    $item instanceof TransferOrderItem ||
                    $item instanceof DeliveryOrderItem ||
                    $item instanceof ReceiptOrderReceivedItem ||
                    $item instanceof InventoryAdjustmentItem
                ) &&
                $item->getInventoryNumber()?->getId() !== $group->getInventoryNumber()?->getId()
            ) {
                continue;
            }

            $itemQuantity = $this->getItemQuantity($item, $isOut);

            $quantity += $itemQuantity;
        }

        if ($isOut) {
            $quantity = - $quantity;
        }

        $difference = $quantity - $group->getQuantity();

        if ($difference === 0.0) {
            return;
        }

        $this->createTransaction(
            $order,
            $group->getProduct()->getId(),
            $difference,
            $group->getType(),
            $group->getInventoryNumber()?->getId(),
            $group->getWarehouse()?->getId()
        );
    }

    private function getItemQuantity(
        QuoteItem|ReceiptOrderReceivedItem $item,
        bool $isOut
    ): ?float {

        if ($item instanceof ReceiptOrderItem) {
            return $item->getQuantityReceived();
        }

        if ($item instanceof TransferOrderItem) {
            return $isOut ?
                $item->getQuantity() :
                $item->getQuantityReceived();
        }

        return $item->getQuantity();
    }

    /**
     * @param iterable<InventoryTransaction> $groups
     */
    private function negate(iterable $groups, Entity $entity, ?string $type = null): void
    {
        foreach ($groups as $group) {
            if ($group->getQuantity() === 0.0) {
                continue;
            }

            if ($type && $group->getType() === $type) {
                continue;
            }

            $this->createTransaction(
                $entity,
                $group->getProduct()->getId(),
                - $group->getQuantity(),
                $group->getType(),
                $group->getInventoryNumber()?->getId(),
                $group->getWarehouse()?->getId()
            );
        }
    }

    /**
     * @param InventoryTransaction::TYPE_* $type
     */
    private function createTransaction(
        Entity $order,
        string $productId,
        float $quantity,
        string $type,
        ?string $inventoryNumberId,
        ?string $warehouseId
    ): void {
        /** @var InventoryTransaction $transaction */
        $transaction = $this->entityManager->getNewEntity(InventoryTransaction::ENTITY_TYPE);

        $transaction
            ->setParent(LinkParent::create($order->getEntityType(), $order->getId()))
            ->setProductId($productId)
            ->setQuantity($quantity)
            ->setType($type)
            ->setWarehouseId($warehouseId)
            ->setInventoryNumberId($inventoryNumberId);

        $this->entityManager->saveEntity($transaction);
    }

    /**
     * @return Collection<InventoryTransaction>
     */
    private function getGroups(Entity $entity, ?string $warehouseId): Collection
    {
        // @todo Order by `number`. Note: MySQL does not support.

        $builder = $this->entityManager
            ->getRDBRepository(InventoryTransaction::ENTITY_TYPE)
            ->select('productId')
            ->select('inventoryNumberId')
            ->select('type')
            ->select(
                Expr::sum(Expr::column('quantity')),
                'quantity'
            )
            ->group('productId')
            ->group('inventoryNumberId')
            ->group('type')
            ->where([
                'parentType' => $entity->getEntityType(),
                'parentId' => $entity->getId(),
            ]);

        if ($this->configDataProvider->isWarehousesEnabled()) {
            $builder
                ->select('warehouseId')
                ->group('warehouseId');
        }

        if ($warehouseId) {
            $builder->where(['warehouseId' => $warehouseId]);
        }

        /** @var Collection<InventoryTransaction> */
        return $builder->find();
    }

    /**
     * @return (QuoteItem|ReceiptOrderReceivedItem)[]
     */
    private function getItems(
        SalesOrder|PurchaseOrder|DeliveryOrder|ReceiptOrder|TransferOrder|InventoryAdjustment $entity,
        string $type
    ): array {

        /** @var Collection<QuoteItem> $items */
        $items = $this->entityManager
            ->getRDBRepository($entity->getEntityType())
            ->getRelation($entity, 'items')
            ->leftJoin('product')
            ->where([
                'productId!=' => null,
                'product.isInventory' => true,
            ])
            ->order('order')
            ->find();

        if ($type === InventoryTransaction::TYPE_SOFT_RESERVE) {
            foreach ($items as $item) {
                $item->clear('inventoryNumberId');
            }
        }

        $items = iterator_to_array($items);

        if ($entity instanceof ReceiptOrder) {
            /** @var ReceiptOrderItem[] $items */

            return $this->getReceivedItems($items, $entity);
        }

        return $items;
    }

    /**
     * @param ReceiptOrderItem[] $items
     * @return ReceiptOrderItem[]
     */
    private function getReceivedItems(array $items, ReceiptOrder $entity): array
    {
        $items = array_values(array_filter($items, fn($item) => $item->getInventoryNumberType() === null));

        /** @var Collection<ReceiptOrderItem> $receivedItems */
        $receivedItems = $this->entityManager
            ->getRDBRepository($entity->getEntityType())
            ->getRelation($entity, 'receivedItems')
            ->order('order')
            ->find();

        foreach ($receivedItems as $item) {
            $items[] = $item;
        }

        return $items;
    }
}
