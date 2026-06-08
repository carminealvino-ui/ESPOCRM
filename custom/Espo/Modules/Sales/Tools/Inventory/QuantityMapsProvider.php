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
use Espo\Modules\Sales\Entities\Quote;
use Espo\Modules\Sales\Entities\SalesOrder;
use Espo\Modules\Sales\Entities\TransferOrder;
use Espo\Modules\Sales\Entities\Warehouse;
use Espo\Modules\Sales\Tools\Inventory\Data\QuantityMaps;
use Espo\Modules\Sales\Tools\Sales\ConfigDataProvider;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\ORM\EntityManager;
use RuntimeException;

class QuantityMapsProvider
{
    public function __construct(
        private ProductQuantityLoader $loader,
        private Metadata $metadata,
        private ConfigDataProvider $configDataProvider,
        private EntityManager $entityManager,
    ) {}

    public function get(DeliveryOrder|TransferOrder|SalesOrder|Quote $entity): QuantityMaps
    {
        if ($this->isNotActual($entity)) {
            return $this->createEmpty();
        }

        $productIds = $entity->getInventoryProductIds();

        if ($productIds === []) {
            return $this->createEmpty();
        }

        $excludeSoftReserve =
            (
                $entity instanceof DeliveryOrder ||
                $entity instanceof TransferOrder
            ) &&
            in_array($entity->getStatus(), $this->getReserveStatusList($entity->getEntityType()));

        $quantityMap = $this->loader->load(
            productIds: $productIds,
            entity: $entity,
            excludeSoftReserve: $excludeSoftReserve,
        );

        $onHandQuantityMap = [];

        if (
            !$excludeSoftReserve &&
            (
                $entity instanceof DeliveryOrder ||
                $entity instanceof TransferOrder
            ) ||
            $entity instanceof SalesOrder
        ) {
            $onHandQuantityMap = $this->loader->load(
                productIds: $productIds,
                entity: $entity,
                excludeSoftReserve: true,
            );
        }

        $totalQuantityMap = [];

        if (
            $this->configDataProvider->isWarehousesEnabled() &&
            $entity instanceof DeliveryOrder &&
            $entity->getWarehouse() &&
            $this->isWarehouseAvailableForStock($entity->getWarehouse()->getId())
        ) {
            $totalQuantityMap = $this->loader->load(
                productIds: $productIds,
                entity: $entity,
                excludeSoftReserve: $excludeSoftReserve,
                forceTotal: true,
            );
        }

        $inventoryQuantityMap = [];

        if (
            $entity instanceof DeliveryOrder ||
            $entity instanceof TransferOrder
        ) {
            $inventoryNumberIds = $entity->getInventoryNumberIds();

            if ($inventoryNumberIds !== []) {
                $inventoryQuantityMap = $this->loader->loadForNumbers($inventoryNumberIds, $entity);
            }
        }

        return new QuantityMaps(
            quantity: $quantityMap,
            onHand: $onHandQuantityMap,
            total: $totalQuantityMap,
            inventory: $inventoryQuantityMap,
        );
    }

    private function isWarehouseAvailableForStock(string $warehouseId): bool
    {
        $warehouse = $this->entityManager
            ->getRDBRepositoryByClass(Warehouse::class)
            ->getById($warehouseId);

        if (!$warehouse) {
            throw new RuntimeException("No warehouse.");
        }

        return $warehouse->isAvailableForStock();
    }

    /**
     * @return string[]
     */
    private function getReserveStatusList(string $entityType): array
    {
        return $this->metadata->get("scopes.$entityType.reserveStatusList") ?? [];
    }

    private function createEmpty(): QuantityMaps
    {
        return new QuantityMaps(
            quantity: [],
            onHand: [],
            total: [],
            inventory: [],
        );
    }

    private function isNotActual(OrderEntity $entity): bool
    {
        $status = $entity->getStatus();
        $entityType = $entity->getEntityType();

        $isDone = in_array($status, $this->getDoneStatusList($entityType));
        $isFailed = in_array($status, $this->getFailedStatusList($entityType));
        $isCanceled = in_array($status, $this->getCanceledStatusList($entityType));

        if ($isCanceled) {
            return true;
        }

        if ($entityType === Quote::ENTITY_TYPE) {
            return $isDone;
        }

        if ($entity instanceof SalesOrder && $entity->isDeliveryCreated()) {
            return true;
        }

        if ($entityType === DeliveryOrder::ENTITY_TYPE || $entityType === TransferOrder::ENTITY_TYPE) {
            $isTransferred =
                !$isDone &&
                !$isFailed &&
                !in_array($status, $this->getReserveStatusList($entityType)) &&
                !in_array($status, $this->getSoftReserveStatusList($entityType));

            return $isDone || $isFailed || $isTransferred;
        }

        if ($entity instanceof SalesOrder) {
            if (!$this->configDataProvider->isDeliveryOrdersEnabled()) {
                return $isDone;
            }

            return $isDone && $entity->isDeliveryCreated();
        }

        return false;
    }

    /**
     * @return string[]
     */
    private function getDoneStatusList(string $entityType): array
    {
        return $this->metadata->get("scopes.$entityType.doneStatusList") ?? [];
    }

    /**
     * @return string[]
     */
    private function getCanceledStatusList(string $entityType): array
    {
        return $this->metadata->get("scopes.$entityType.canceledStatusList") ?? [];
    }

    /**
     * @return string[]
     */
    private function getFailedStatusList(string $entityType): array
    {
        return $this->metadata->get("scopes.$entityType.failedStatusList") ?? [];
    }

    /**
     * @return string[]
     */
    private function getSoftReserveStatusList(string $entityType): array
    {
        return $this->metadata->get("scopes.$entityType.softReserveStatusList") ?? [];
    }
}
