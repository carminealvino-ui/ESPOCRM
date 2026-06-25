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
use Espo\Modules\Sales\Entities\InventoryTransaction;
use Espo\Modules\Sales\Entities\TransferOrder;
use Espo\Modules\Sales\Entities\TransferOrderItem;
use Espo\ORM\EntityManager;

class TransferOrderProcessor
{
    public function __construct(
        private EntityManager $entityManager,
        private Metadata $metadata,
        private TransactionManager $transactionManager
    ) {}

    public function processSave(TransferOrder $order): void
    {
        $this->startTransactionAndLock($order);

        $toTransferIn = false;

        if ($this->toSoftReserve($order)) {
            $this->transactionManager->adjustOut($order, InventoryTransaction::TYPE_SOFT_RESERVE);
        }
        else if ($this->toReserve($order)) {
            $this->transactionManager->adjustOut($order, InventoryTransaction::TYPE_RESERVE);
        }
        else if ($this->toTransfer($order)) {
            $this->transactionManager->adjustOut($order, InventoryTransaction::TYPE_TRANSFER);

            $toTransferIn = $this->toTransferReceipt($order);
        }
        else {
            $this->transactionManager->adjustOut($order);
        }

        if ($toTransferIn) {
            $this->transactionManager->adjustIn($order, InventoryTransaction::TYPE_TRANSFER);
        }
        else {
            $this->transactionManager->adjustIn($order);
        }

        $this->entityManager
            ->getTransactionManager()
            ->commit();
    }

    public function processRemove(TransferOrder $order): void
    {
        $this->transactionManager->adjustOut($order);
    }

    private function startTransactionAndLock(TransferOrder $order): void
    {
        $this->entityManager
            ->getTransactionManager()
            ->start();

        $this->entityManager
            ->getRDBRepository(TransferOrder::ENTITY_TYPE)
            ->sth()
            ->select('id')
            ->forUpdate()
            ->where(['id' => $order->getId()])
            ->find();

        $this->entityManager
            ->getRDBRepository(TransferOrderItem::ENTITY_TYPE)
            ->sth()
            ->select('id')
            ->forUpdate()
            ->where(['transferOrderId' => $order->getId()])
            ->find();

        $this->entityManager
            ->getRDBRepository(InventoryTransaction::ENTITY_TYPE)
            ->sth()
            ->select('id')
            ->forUpdate()
            ->where(['parentType' => TransferOrder::ENTITY_TYPE])
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

    private function toTransfer(TransferOrder $order): bool
    {
        $status = $order->getStatus();

        return
            !in_array($status, $this->getCanceledStatusList()) &&
            !in_array($status, $this->getReserveStatusList());
    }

    private function toTransferReceipt(TransferOrder $order): bool
    {
        $status = $order->getStatus();

        return in_array($status, $this->getDoneStatusList());
    }

    private function toSoftReserve(TransferOrder $order): bool
    {
        $status = $order->getStatus();

        return
            in_array($status, $this->getSoftReserveStatusList()) &&
            !in_array($status, $this->getCanceledStatusList());
    }

    private function toReserve(TransferOrder $order): bool
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
        return $this->metadata->get('scopes.TransferOrder.canceledStatusList', []);
    }

    /**
     * @return string[]
     */
    private function getSoftReserveStatusList(): array
    {
        return $this->metadata->get('scopes.TransferOrder.softReserveStatusList', []);
    }

    /**
     * @return string[]
     */
    private function getReserveStatusList(): array
    {
        return $this->metadata->get('scopes.TransferOrder.reserveStatusList', []);
    }

    /**
     * @return string[]
     */
    private function getDoneStatusList(): array
    {
        return $this->metadata->get('scopes.TransferOrder.doneStatusList', []);
    }
}
