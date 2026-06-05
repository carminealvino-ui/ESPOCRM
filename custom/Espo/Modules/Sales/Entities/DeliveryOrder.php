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

namespace Espo\Modules\Sales\Entities;

use Espo\Core\Field\Currency;
use Espo\Core\Field\Date;
use Espo\Core\Field\Link;
use Espo\Modules\Sales\Tools\Inventory\Data\ProductIdNumberIdPair;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\Modules\Sales\Tools\Sales\OrderItem;

/**
 * @extends OrderEntity<OrderItem>
 */
class DeliveryOrder extends OrderEntity
{
    public const ENTITY_TYPE = 'DeliveryOrder';

    public const STATUS_DRAFT = 'Draft';
    public const STATUS_READY = 'Ready';
    public const STATUS_IN_PROGRESS = 'In Progress';
    public const STATUS_COMPLETED = 'Completed';

    public const FIELD_STATUS = 'status';

    public function setSalesOrderId(?string $id): self
    {
        $this->set('salesOrderId', $id);

        return $this;
    }

    public function setWarehouseId(?string $id): self
    {
        $this->set('warehouseId', $id);

        return $this;
    }

    public function getSalesOrder(): ?Link
    {
        /** @var ?Link */
        return $this->getValueObject('salesOrder');
    }

    /**
     * @todo Rename to `getSalesOrder` when v9.0 is min supported.
     * @internal
     */
    public function getSalesOrderEntity(): ?SalesOrder
    {
        /** @var ?SalesOrder */
        return $this->relations->getOne('salesOrder');
    }

    public function getWarehouse(): ?Link
    {
        /** @var ?Link */
        return $this->getValueObject('warehouse');
    }

    /**
     * @return OrderItem[]
     */
    public function getItems(): array
    {
        return array_map(
            fn ($item) => OrderItem::fromRaw($item),
            $this->get(OrderEntity::ATTR_ITEM_LIST) ?? []
        );
    }

    /**
     * @return array<string, float>
     */
    public function getInventoryPairQuantityMap(): array
    {
        $map = [];

        foreach ($this->get(OrderEntity::ATTR_ITEM_LIST) ?? [] as $item) {
            $productId = $item->productId ?? null;
            $quantity = $item->quantity ?? null;
            $inventoryNumberId = $item->inventoryNumberId ?? null;
            $isInventory = $item->isInventory ?? false;

            if (!$productId || !$quantity || !$isInventory) {
                continue;
            }

            $key = $productId . '_' . ($inventoryNumberId ?? '');

            $map[$key] ??= 0.0;
            $map[$key] += $quantity;
        }

        return $map;
    }

    /**
     * @return ProductIdNumberIdPair[]
     */
    public function getInventoryPairs(): array
    {
        $pairs = array_keys($this->getInventoryPairQuantityMap());

        return array_map(function (string $item) {
            [$productId, $numberId] = explode('_', $item);

            if ($numberId === '') {
                $numberId = null;
            }

            return new ProductIdNumberIdPair($productId, $numberId);
        }, $pairs);
    }

    /**
     * @return string[]
     */
    public function getInventoryNumberIds(): array
    {
        $ids = [];

        foreach ($this->get(OrderEntity::ATTR_ITEM_LIST) ?? [] as $item) {
            $inventoryNumberId = $item->inventoryNumberId ?? null;

            if ($inventoryNumberId) {
                $ids[] = $inventoryNumberId;
            }
        }

        return $ids;
    }

    public function getDateOrdered(): ?Date
    {
        /** @var ?Date */
        return $this->getValueObject('dateOrdered');
    }

    public function getShippingDate(): ?Date
    {
        /** @var ?Date */
        return $this->getValueObject('shippingDate');
    }

    public function setShippingCost(?Currency $cost): self
    {
        return $this->setValueObject(OrderEntity::FIELD_SHIPPING_COST, $cost);
    }
}
