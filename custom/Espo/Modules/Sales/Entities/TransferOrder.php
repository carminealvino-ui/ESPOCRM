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
use Espo\Modules\Sales\Tools\Sales\ReceivedOrderItem;
use RuntimeException;

/**
 * @extends OrderEntity<OrderItem>
 */
class TransferOrder extends OrderEntity
{
    public const ENTITY_TYPE = 'TransferOrder';

    public const STATUS_READY = 'Ready';
    public const STATUS_IN_PROGRESS = 'In Progress';
    public const STATUS_COMPLETED = 'Completed';

    public function getAccount(): ?Link
    {
        return null;
    }

    public function getFromWarehouse(): Link
    {
        /** @var ?Link $value */
        $value =  $this->getValueObject('fromWarehouse');

        if (!$value) {
            throw new RuntimeException("No from-warehouse.");
        }

        return $value;
    }

    public function getToWarehouse(): Link
    {
        /** @var ?Link $value */
        $value =  $this->getValueObject('toWarehouse');

        if (!$value) {
            throw new RuntimeException("No to-warehouse.");
        }

        return $value;
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

    public function getDeliveryDate(): ?Date
    {
        /** @var ?Date */
        return $this->getValueObject('deliveryDate');
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

    /**
     * @return ReceivedOrderItem[]
     */
    public function getItems(): array
    {
        return array_map(
            fn ($item) => ReceivedOrderItem::fromRaw($item)
                ->withQuantityReceived($item->quantityReceived ?? null),
            $this->get(OrderEntity::ATTR_ITEM_LIST) ?? []
        );
    }

    public function setFromWarehouseId(string $value): self
    {
        $this->set('fromWarehouseId', $value);

        return $this;
    }

    public function setToWarehouseId(string $value): self
    {
        $this->set('toWarehouseId', $value);

        return $this;
    }

    public function getShippingCost(): ?Currency
    {
        /** @var ?Currency */
        return $this->getValueObject(OrderEntity::FIELD_SHIPPING_COST);
    }

    public function setShippingCost(?Currency $cost): self
    {
        return $this->setValueObject(OrderEntity::FIELD_SHIPPING_COST, $cost);
    }
}
