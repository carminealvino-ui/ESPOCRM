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
use Espo\Modules\Sales\Entities\PurchaseOrder;
use Espo\Modules\Sales\Entities\PurchaseOrderItem;
use Espo\ORM\EntityManager;

class PurchaseOrderProcessor
{
    public function __construct(
        private EntityManager $entityManager,
        private Metadata $metadata,
        private TransactionManager $transactionManager
    ) {}

    public function processSave(PurchaseOrder $purchaseOrder): void
    {
        $this->startTransactionAndLock($purchaseOrder);

        if ($this->toTransfer($purchaseOrder)) {
            $this->transactionManager->adjustIn($purchaseOrder, InventoryTransaction::TYPE_TRANSFER);
        }
        else {
            $this->transactionManager->adjustIn($purchaseOrder);
        }

        $this->entityManager
            ->getTransactionManager()
            ->commit();
    }

    public function processRemove(PurchaseOrder $purchaseOrder): void
    {
        $this->transactionManager->adjustIn($purchaseOrder);
    }

    private function toTransfer(PurchaseOrder $salesOrder): bool
    {
        if (!in_array($salesOrder->getStatus(), $this->getDoneStatusList())) {
            return false;
        }

        return true;
    }

    private function startTransactionAndLock(PurchaseOrder $purchaseOrder): void
    {
        $this->entityManager
            ->getTransactionManager()
            ->start();

        $this->entityManager
            ->getRDBRepository(PurchaseOrder::ENTITY_TYPE)
            ->sth()
            ->select('id')
            ->forUpdate()
            ->where(['id' => $purchaseOrder->getId()])
            ->find();

        $this->entityManager
            ->getRDBRepository(PurchaseOrderItem::ENTITY_TYPE)
            ->sth()
            ->select('id')
            ->forUpdate()
            ->where(['purchaseOrderId' => $purchaseOrder->getId()])
            ->find();

        $this->entityManager
            ->getRDBRepository(InventoryTransaction::ENTITY_TYPE)
            ->sth()
            ->select('id')
            ->forUpdate()
            ->where(['parentType' => PurchaseOrder::ENTITY_TYPE])
            ->where(['parentId' => $purchaseOrder->getId()])
            ->find();
    }

    /**
     * @return string[]
     */
    private function getDoneStatusList(): array
    {
        return $this->metadata->get('scopes.PurchaseOrder.doneStatusList', []);
    }
}
