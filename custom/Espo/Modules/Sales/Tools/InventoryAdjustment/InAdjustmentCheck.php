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

use Espo\Modules\Sales\Entities\DeliveryOrder;
use Espo\Modules\Sales\Entities\InventoryAdjustment;
use Espo\Modules\Sales\Entities\InventoryTransaction;
use Espo\Modules\Sales\Entities\ReceiptOrder;
use Espo\Modules\Sales\Entities\TransferOrder;
use Espo\ORM\EntityManager;
use Espo\ORM\Query\Part\Condition;
use Espo\ORM\Query\Part\Expression as Expr;

class InAdjustmentCheck
{
    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function check(DeliveryOrder|TransferOrder|ReceiptOrder $order): bool
    {
        $warehouseIds = [];

        if ($order instanceof TransferOrder) {
            $warehouseIds = [
                $order->getFromWarehouse()->getId(),
                $order->getToWarehouse()->getId(),
            ];
        }
        else if ($order->getWarehouse()) {
            $warehouseIds[] = $order->getWarehouse()->getId();
        }

        $adjustments = $this->entityManager
            ->getRDBRepositoryByClass(InventoryAdjustment::class)
            ->where([
                'warehouseId' => $warehouseIds,
                'status' => InventoryAdjustment::STATUS_STARTED,
            ])
            ->sth()
            ->find();

        $transactions = null;

        foreach ($adjustments as $adjustment) {
            $transactions ??= $this->getTransactions($order);

            if (!$this->checkAdjustment($adjustment, $order, $transactions)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param iterable<InventoryTransaction> $transactions
     */
    private function checkAdjustment(
        InventoryAdjustment $adjustment,
        DeliveryOrder|TransferOrder|ReceiptOrder $order,
        iterable $transactions
    ): bool {

        $items = $order instanceof ReceiptOrder ?
            $order->getReceivedItems() :
            $order->getItems();

        $adjustment->loadItemListField();

        foreach ($adjustment->getItems() as $adjItem) {
            foreach ($items as $item) {
                if (
                    $adjItem->getProductId() === $item->getProductId() &&
                    $adjItem->getInventoryNumberId() === $item->getInventoryNumberId()
                ) {
                    return false;
                }
            }

            foreach ($transactions as $transaction) {
                if (
                    $adjItem->getProductId() === $transaction->getProduct()->getId() &&
                    $adjItem->getInventoryNumberId() === $transaction->getInventoryNumber()?->getId()
                ) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @return iterable<InventoryTransaction>
     */
    private function getTransactions(DeliveryOrder|TransferOrder|ReceiptOrder $order): iterable
    {
        if ($order->isNew()) {
            return [];
        }

        $expr = Expr::sum(Expr::column('quantity'));

        return $this->entityManager
            ->getRDBRepositoryByClass(InventoryTransaction::class)
            ->select([
                'id',
                'productId',
                'warehouseId',
                'inventoryNumberId',
            ])
            ->select($expr)
            ->where([
                'parentId' => $order->getId(),
                'parentType' => $order->getEntityType(),
            ])
            ->having(
                Condition::notEqual($expr, 0.0)
            )
            ->group('productId')
            ->group('warehouseId')
            ->group('inventoryNumberId')
            ->find();
    }
}
