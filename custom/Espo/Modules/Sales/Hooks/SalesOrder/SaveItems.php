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

namespace Espo\Modules\Sales\Hooks\SalesOrder;

use Espo\Modules\Sales\Entities\SalesOrder;
use Espo\Modules\Sales\Hooks\Quote\SaveItems as QuoteSaveItems;
use Espo\Modules\Sales\Tools\Inventory\SalesOrderProcessor;
use Espo\Modules\Sales\Tools\Inventory\WebSocketSubmitter;
use Espo\Modules\Sales\Tools\Sales\ConfigDataProvider;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/** @noinspection PhpUnused */
class SaveItems
{
    public function __construct(
        private QuoteSaveItems $hook,
        private SalesOrderProcessor $salesOrderProcessor,
        private EntityManager $entityManager,
        private WebSocketSubmitter $webSocketSubmitter,
        private ConfigDataProvider $configDataProvider
    ) {}

    /**
     * @param SalesOrder $entity
     * @param array<string, mixed> $options
     */
    public function afterSave(Entity $entity, array $options): void
    {
        $toSubmit = false;

        $this->entityManager
            ->getTransactionManager()
            ->run(function () use ($entity, $options, &$toSubmit) {
                $this->hook->afterSave($entity, $options);

                if (!$this->toProcessInventorySave($entity)) {
                    return;
                }

                $this->salesOrderProcessor->processSave($entity);

                $toSubmit = true;
            });

        if ($toSubmit) {
            $this->webSocketSubmitter->process($entity);
        }
    }

    private function toProcessInventorySave(SalesOrder $entity): bool
    {
        return
            $this->configDataProvider->isInventoryTransactionsEnabled() &&
            $entity->has('isDeliveryCreated') &&
            !$entity->isLocked() &&
            (
                $entity->isNew() ||
                $entity->isAttributeChanged('status') ||
                $entity->isAttributeChanged(OrderEntity::ATTR_ITEM_LIST) ||
                (
                    $this->configDataProvider->isDeliveryOrdersEnabled() &&
                    $entity->isAttributeChanged('isDeliveryCreated')
                )
            );
    }

    /**
     * @param SalesOrder $entity
     */
    public function afterRemove(Entity $entity): void
    {
        $this->hook->afterRemove($entity);

        if (
            $this->configDataProvider->isInventoryTransactionsEnabled() &&
            !$entity->isLocked()
        ) {
            $this->salesOrderProcessor->processRemove($entity);
        }
    }
}
