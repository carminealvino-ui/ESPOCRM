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

namespace Espo\Modules\Sales\Hooks\ReceiptOrder;

use Espo\Modules\Sales\Entities\PurchaseOrder;
use Espo\Modules\Sales\Entities\ReceiptOrder;
use Espo\Modules\Sales\Entities\ReturnOrder;
use Espo\Modules\Sales\Hooks\Quote\SaveItems as QuoteSaveItems;
use Espo\Modules\Sales\Tools\Inventory\ReceiptOrderProcessor;
use Espo\Modules\Sales\Tools\Inventory\WebSocketSubmitter;
use Espo\Modules\Sales\Tools\PurchaseOrder\ReceiptService;
use Espo\Modules\Sales\Tools\ReceiptOrder\ReceivedItemsRemoveProcessor;
use Espo\Modules\Sales\Tools\ReceiptOrder\ReceivedItemsSaveProcessor;
use Espo\Modules\Sales\Tools\ReceiptOrder\ValidationHelper;
use Espo\Modules\Sales\Tools\Sales\ConfigDataProvider;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/** @noinspection PhpUnused */
class SaveItems
{
    public function __construct(
        private QuoteSaveItems $hook,
        private ReceiptOrderProcessor $receiptOrderProcessor,
        private EntityManager $entityManager,
        private WebSocketSubmitter $webSocketSubmitter,
        private ReceiptService $receiptService,
        private ConfigDataProvider $configDataProvider,
        private ValidationHelper $validationHelper,
        private ReceivedItemsSaveProcessor $receivedItemsSaveProcessor,
        private ReceivedItemsRemoveProcessor $receivedItemsRemoveProcessor
    ) {}

    /**
     * @param ReceiptOrder $entity
     * @param array<string, mixed> $options
     */
    public function afterSave(Entity $entity, array $options): void
    {
        $toSubmit = false;

        $this->entityManager
            ->getTransactionManager()
            ->run(function () use ($entity, $options, &$toSubmit) {
                $this->hook->afterSave($entity, $options);

                $this->processReceivedItemsSave($entity, $options);
                $this->processPurchaseOrderUpdate($entity);
                $this->processReturnOrderUpdate($entity);

                if (!$this->validationHelper->toProcessInventorySave($entity)) {
                    return;
                }

                $this->receiptOrderProcessor->processSave($entity);

                $toSubmit = true;
            });

        if ($toSubmit) {
            $this->webSocketSubmitter->process($entity);
        }
    }

    /**
     * @param ReceiptOrder $entity
     */
    public function afterRemove(Entity $entity): void
    {
        $this->hook->afterRemove($entity);
        $this->receivedItemsRemoveProcessor->process($entity);

        if (
            $this->configDataProvider->isInventoryTransactionsEnabled() &&
            !$entity->isLocked()
        ) {
            $this->receiptOrderProcessor->processRemove($entity);
        }

        $this->processPurchaseOrderUpdate($entity);
        $this->processReturnOrderUpdate($entity);
    }

    private function processPurchaseOrderUpdate(ReceiptOrder $entity): void
    {
        if (
            !$this->configDataProvider->isReceiptOrdersEnabled() ||
            !$entity->getPurchaseOrder()
        ) {
            return;
        }

        /** @var ?PurchaseOrder $purchaseOrder */
        $purchaseOrder = $this->entityManager
            ->getRDBRepository(PurchaseOrder::ENTITY_TYPE)
            ->getById($entity->getPurchaseOrder()->getId());

        if (!$purchaseOrder) {
            return;
        }

        $this->receiptService->controlReceiptFullyCreated($purchaseOrder);
    }

    private function processReturnOrderUpdate(ReceiptOrder $entity): void
    {
        if (
            !$this->configDataProvider->isReceiptOrdersEnabled() ||
            !$entity->getReturnOrder()
        ) {
            return;
        }

        /** @var ?ReturnOrder $returnOrder */
        $returnOrder = $this->entityManager
            ->getRDBRepository(ReturnOrder::ENTITY_TYPE)
            ->getById($entity->getReturnOrder()->getId());

        if (!$returnOrder) {
            return;
        }

        $this->receiptService->controlReceiptFullyCreated($returnOrder);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function processReceivedItemsSave(ReceiptOrder $entity, array $options): void
    {
        if (!empty($options['skipWorkflow']) && empty($options['addItemList'])) {
            return;
        }

        $isNew = $entity->isNew();

        if ($options['forceIsNotNew'] ?? false) {
            $isNew = false;
        }

        $this->receivedItemsSaveProcessor->process($entity, $isNew);
    }
}
