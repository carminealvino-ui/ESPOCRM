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
use Espo\Modules\Sales\Entities\SalesOrder;
use Espo\Modules\Sales\Entities\SalesOrderItem;
use Espo\Modules\Sales\Tools\Sales\ConfigDataProvider;
use Espo\ORM\EntityManager;

class SalesOrderProcessor
{
    public function __construct(
        private EntityManager $entityManager,
        private Metadata $metadata,
        private TransactionManager $transactionManager,
        private ConfigDataProvider $configDataProvider
    ) {}

    public function processSave(SalesOrder $salesOrder): void
    {
        $this->startTransactionAndLock($salesOrder);

        if ($this->toSoftReserve($salesOrder)) {
            $this->transactionManager->adjustOut($salesOrder, InventoryTransaction::TYPE_SOFT_RESERVE);
        }
        else if ($this->toTransfer($salesOrder)) {
            $this->transactionManager->adjustOut($salesOrder, InventoryTransaction::TYPE_TRANSFER);
        }
        else if ($this->toCancel($salesOrder)) {
            $this->transactionManager->adjustOut($salesOrder);
        }

        $this->entityManager
            ->getTransactionManager()
            ->commit();
    }

    public function processRemove(SalesOrder $salesOrder): void
    {
        $this->transactionManager->adjustOut($salesOrder);
    }

    private function startTransactionAndLock(SalesOrder $salesOrder): void
    {
        $this->entityManager
            ->getTransactionManager()
            ->start();

        $this->entityManager
            ->getRDBRepository(SalesOrder::ENTITY_TYPE)
            ->sth()
            ->select('id')
            ->forUpdate()
            ->where(['id' => $salesOrder->getId()])
            ->find();

        $this->entityManager
            ->getRDBRepository(SalesOrderItem::ENTITY_TYPE)
            ->sth()
            ->select('id')
            ->forUpdate()
            ->where(['salesOrderId' => $salesOrder->getId()])
            ->find();

        $this->entityManager
            ->getRDBRepository(InventoryTransaction::ENTITY_TYPE)
            ->sth()
            ->select('id')
            ->forUpdate()
            ->where(['parentType' => SalesOrder::ENTITY_TYPE])
            ->where(['parentId' => $salesOrder->getId()])
            ->find();
    }

    private function toSoftReserve(SalesOrder $salesOrder): bool
    {
        if (
            $this->configDataProvider->isDeliveryOrdersEnabled() &&
            $salesOrder->isDeliveryCreated()
        ) {
            return false;
        }

        if (
            !in_array($salesOrder->getStatus(), $this->getSoftReserveStatusList()) &&
            !in_array($salesOrder->getStatus(), $this->getDoneStatusList())
        ) {
            return false;
        }

        // Transfer is handled by delivery orders.
        if ($this->configDataProvider->isDeliveryOrdersEnabled()) {
            return true;
        }

        if (in_array($salesOrder->getStatus(), $this->getDoneStatusList())) {
            return false;
        }

        return true;
    }

    private function toTransfer(SalesOrder $salesOrder): bool
    {
        // Transfer is handled by delivery orders.
        if ($this->configDataProvider->isDeliveryOrdersEnabled()) {
            return false;
        }

        if (!in_array($salesOrder->getStatus(), $this->getDoneStatusList())) {
            return false;
        }

        return true;
    }

    private function toCancel(SalesOrder $salesOrder): bool
    {
        if (
            $this->configDataProvider->isDeliveryOrdersEnabled() &&
            $salesOrder->isDeliveryCreated()
        ) {
            return true;
        }

        if ($salesOrder->isNew()) {
            return false;
        }

        // @todo Check whether the following code is needed.

        $statusList = array_merge(
            $this->getSoftReserveStatusList(),
            $this->getDoneStatusList(),
        );

        if (in_array($salesOrder->getStatus(), $statusList)) {
            return false;
        }

        if (!in_array($salesOrder->getFetchedStatus(), $statusList)) {
            return false;
        }

        /*if (!in_array($salesOrder->getStatus(), $this->getCancelStatusList())) {
            return false;
        }

        if (in_array($salesOrder->getFetchedStatus(), $this->getCancelStatusList())) {
            return false;
        }*/

        return true;
    }

    /**
     * @return string[]
     */
    private function getSoftReserveStatusList(): array
    {
        return $this->metadata->get('scopes.SalesOrder.softReserveStatusList', []);
    }

    /**
     * @return string[]
     */
    private function getDoneStatusList(): array
    {
        return $this->metadata->get('scopes.SalesOrder.doneStatusList', []);
    }
}
