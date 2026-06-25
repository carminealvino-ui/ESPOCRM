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

namespace Espo\Modules\Sales\Tools\InventoryAdjustment;

use Espo\Modules\Sales\Entities\InventoryAdjustment;
use Espo\Modules\Sales\Entities\InventoryAdjustmentItem;
use Espo\Modules\Sales\Entities\InventoryTransaction;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\Modules\Sales\Tools\Sales\OrderItem;
use Espo\ORM\EntityManager;
use Espo\ORM\Query\Part\Expression as Expr;
use Espo\ORM\Query\SelectBuilder;
use RuntimeException;

class ItemQuantity
{
    private const ATTR_NEW_ON_HAND = 'newQuantityOnHand';

    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function process(InventoryAdjustment $order): void
    {
        $this->loadItems($order);

        $newItems = [];

        foreach ($order->getItems() as $item) {
            $newItems[] = $this->amendItem($item, $order);
        }

        $order->setItems($newItems);
    }

    private function amendItem(OrderItem $item, InventoryAdjustment $order): OrderItem
    {
        $quantity = $this->getQuantity($item, $order);

        $this->saveItemQuantity($item, $quantity);

        return $item->withQuantity($quantity);
    }

    private function loadItems(InventoryAdjustment $order): void
    {
        $fetched = $order->getFetched(OrderEntity::ATTR_ITEM_LIST);
        $order->loadItemListField();
        $order->setFetched(OrderEntity::ATTR_ITEM_LIST, $fetched);
    }

    private function getQuantity(OrderItem $item, InventoryAdjustment $order): float
    {
        if ($item->get(self::ATTR_NEW_ON_HAND) === null) {
            return 0.0;
        }

        $currentQuantity = $this->getCurrentQuantity($item, $order);

        return $item->get(self::ATTR_NEW_ON_HAND) - $currentQuantity;
    }

    private function saveItemQuantity(OrderItem $item, float $quantity): void
    {
        if (!$item->getId()) {
            throw new RuntimeException("No item ID.");
        }

        $itemEntity = $this->entityManager
            ->getRDBRepositoryByClass(InventoryAdjustmentItem::class)
            ->getById($item->getId());

        if (!$itemEntity) {
            throw new RuntimeException("Item {$item->getId()} not found.");
        }

        $itemEntity->set('quantity', $quantity);

        $this->entityManager->saveEntity($itemEntity);
    }

    private function getCurrentQuantity(OrderItem $item, InventoryAdjustment $order): float
    {
        $query =
            SelectBuilder::create()
                ->from(InventoryTransaction::ENTITY_TYPE)
                ->select(
                    Expr::sum(Expr::column('quantity')),
                    'sum'
                )
                ->where([
                    'productId' => $item->getProductId(),
                    'inventoryNumberId' => $item->getInventoryNumberId(),
                    'type!=' => InventoryTransaction::TYPE_SOFT_RESERVE,
                    'warehouseId' => $order->getWarehouse()?->getId(),
                ])
                ->build();

        $sth = $this->entityManager->getQueryExecutor()->execute($query);

        $row = $sth->fetch();

        if (!$row) {
            return 0.0;
        }

        return (float) $row['sum'];
    }
}
