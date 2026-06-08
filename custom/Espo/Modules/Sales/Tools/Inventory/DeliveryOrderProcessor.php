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

use Espo\Core\Utils\Metadata;
use Espo\Modules\Sales\Entities\DeliveryOrder;
use Espo\Modules\Sales\Entities\DeliveryOrderItem;
use Espo\Modules\Sales\Entities\InventoryTransaction;
use Espo\ORM\EntityManager;

class DeliveryOrderProcessor
{
    public function __construct(
        private EntityManager $entityManager,
        private Metadata $metadata,
        private TransactionManager $transactionManager
    ) {}

    public function processSave(DeliveryOrder $order): void
    {
        $this->startTransactionAndLock($order);

        if ($this->toSoftReserve($order)) {
            $this->transactionManager->adjustOut($order, InventoryTransaction::TYPE_SOFT_RESERVE);
        }
        else if ($this->toReserve($order)) {
            $this->transactionManager->adjustOut($order, InventoryTransaction::TYPE_RESERVE);
        }
        else if ($this->toTransfer($order)) {
            $this->transactionManager->adjustOut($order, InventoryTransaction::TYPE_TRANSFER);
        }
        else {
            $this->transactionManager->adjustOut($order);
        }

        $this->entityManager
            ->getTransactionManager()
            ->commit();
    }

    public function processRemove(DeliveryOrder $order): void
    {
        $this->transactionManager->adjustOut($order);
    }

    private function startTransactionAndLock(DeliveryOrder $order): void
    {
        $this->entityManager
            ->getTransactionManager()
            ->start();

        $this->entityManager
            ->getRDBRepository(DeliveryOrder::ENTITY_TYPE)
            ->sth()
            ->select('id')
            ->forUpdate()
            ->where(['id' => $order->getId()])
            ->find();

        $this->entityManager
            ->getRDBRepository(DeliveryOrderItem::ENTITY_TYPE)
            ->sth()
            ->select('id')
            ->forUpdate()
            ->where(['deliveryOrderId' => $order->getId()])
            ->find();

        $this->entityManager
            ->getRDBRepository(InventoryTransaction::ENTITY_TYPE)
            ->sth()
            ->select('id')
            ->forUpdate()
            ->where(['parentType' => DeliveryOrder::ENTITY_TYPE])
            ->where(['parentId' => $order->getId()])
            ->find();

        $productIds = $order->getInventoryProductIds();

        if ($productIds !== []) {
            $this->entityManager
                ->getRDBRepository(InventoryTransaction::ENTITY_TYPE)
                ->sth()
                ->select('id')
                ->forUpdate()
                ->where(['productId' => $productIds])
                ->find();
        }
    }

    private function toTransfer(DeliveryOrder $order): bool
    {
        $status = $order->getStatus();

        return
            !in_array($status, $this->getCanceledStatusList()) &&
            !in_array($status, $this->getReserveStatusList());
    }

    private function toSoftReserve(DeliveryOrder $order): bool
    {
        $status = $order->getStatus();

        return
            in_array($status, $this->getSoftReserveStatusList()) &&
            !in_array($status, $this->getCanceledStatusList());
    }

    private function toReserve(DeliveryOrder $order): bool
    {
        $status = $order->getStatus();

        return
            in_array($status, $this->getReserveStatusList()) &&
            !in_array($status, $this->getCanceledStatusList());
    }

    /**
     * @return string[]
     */
    private function getCanceledStatusList(): array
    {
        return $this->metadata->get('scopes.DeliveryOrder.canceledStatusList', []);
    }

    /**
     * @return string[]
     */
    private function getSoftReserveStatusList(): array
    {
        return $this->metadata->get('scopes.DeliveryOrder.softReserveStatusList', []);
    }

    /**
     * @return string[]
     */
    private function getReserveStatusList(): array
    {
        return $this->metadata->get('scopes.DeliveryOrder.reserveStatusList', []);
    }
}
