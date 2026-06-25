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

namespace Espo\Modules\Sales\Hooks\DeliveryOrder;

use Espo\Modules\Sales\Entities\DeliveryOrder;
use Espo\Modules\Sales\Entities\SalesOrder;
use Espo\Modules\Sales\Hooks\Quote\SaveItems as QuoteSaveItems;
use Espo\Modules\Sales\Tools\DeliveryOrder\ValidationHelper;
use Espo\Modules\Sales\Tools\Inventory\DeliveryOrderProcessor;
use Espo\Modules\Sales\Tools\Inventory\WebSocketSubmitter;
use Espo\Modules\Sales\Tools\Sales\ConfigDataProvider;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/** @noinspection PhpUnused */
class SaveItems
{
    public function __construct(
        private QuoteSaveItems $hook,
        private DeliveryOrderProcessor $deliveryOrderProcessor,
        private EntityManager $entityManager,
        private WebSocketSubmitter $webSocketSubmitter,
        private ConfigDataProvider $configDataProvider,
        private ValidationHelper $validationHelper
    ) {}

    /**
     * @param DeliveryOrder $entity
     * @param array<string, mixed> $options
     */
    public function afterSave(Entity $entity, array $options): void
    {
        $toSubmit = false;

        $this->entityManager
            ->getTransactionManager()
            ->run(function () use ($entity, $options, &$toSubmit) {
                $this->processSalesOrderUpdate($entity);

                $this->hook->afterSave($entity, $options);

                if (!$this->validationHelper->toProcessInventorySave($entity)) {
                    return;
                }

                $this->deliveryOrderProcessor->processSave($entity);

                $toSubmit = true;
            });

        if ($toSubmit) {
            $this->webSocketSubmitter->process($entity);
        }
    }

    /**
     * @param DeliveryOrder $entity
     */
    public function afterRemove(Entity $entity): void
    {
        $this->hook->afterRemove($entity);

        if (
            $this->configDataProvider->isInventoryTransactionsEnabled() &&
            !$entity->isLocked()
        ) {
            $this->deliveryOrderProcessor->processRemove($entity);
        }

        $this->processAfterRemoveSalesOrderUpdate($entity);
    }

    private function processSalesOrderUpdate(DeliveryOrder $entity): void
    {
        if (!$entity->isNew() || !$entity->getSalesOrder()) {
            return;
        }

        $this->entityManager
            ->getRDBRepositoryByClass(SalesOrder::class)
            ->select(['id'])
            ->forUpdate()
            ->where(['id' => $entity->getSalesOrder()->getId()])
            ->findOne();

        $salesOrder = $this->entityManager
            ->getRDBRepositoryByClass(SalesOrder::class)
            ->getById($entity->getSalesOrder()->getId());

        if (!$salesOrder || $salesOrder->isDeliveryCreated()) {
            return;
        }

        $salesOrder->set('isDeliveryCreated', true);

        $this->entityManager->saveEntity($salesOrder);
    }

    private function processAfterRemoveSalesOrderUpdate(DeliveryOrder $entity): void
    {
        if (!$this->configDataProvider->isDeliveryOrdersEnabled() || !$entity->getSalesOrder()) {
            return;
        }

        $id = $entity->getSalesOrder()->getId();

        $count = $this->entityManager
            ->getRDBRepository(DeliveryOrder::ENTITY_TYPE)
            ->where(['salesOrderId' => $id])
            ->count();

        if ($count) {
            return;
        }

        $salesOrder = $this->entityManager->getEntityById(SalesOrder::ENTITY_TYPE, $id);

        if (!$salesOrder) {
            return;
        }

        $salesOrder->set('isDeliveryCreated', false);

        $this->entityManager->saveEntity($salesOrder);
    }
}
