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

use Espo\Core\Field\Date;
use Espo\Core\Field\Link;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\Modules\Sales\Tools\Sales\OrderItem;
use Espo\Modules\Sales\Tools\Sales\ReceivedOrderItem;

/**
 * @extends OrderEntity<OrderItem>
 */
class ReceiptOrder extends OrderEntity
{
    public const ENTITY_TYPE = 'ReceiptOrder';

    public const STATUS_COMPLETED = 'Completed';

    public const FIELD_STATUS = 'status';

    public const ATTR_RECEIVED_ITEM_LIST = 'receivedItemList';

    public function getPurchaseOrder(): ?Link
    {
        /** @var ?Link */
        return $this->getValueObject('purchaseOrder');
    }

    public function getReturnOrder(): ?Link
    {
        /** @var ?Link */
        return $this->getValueObject('returnOrder');
    }

    /**
     * @todo Rename to `getSalesOrder` when v9.0 is min supported.
     * @internal
     */
    public function getPurchaseOrderEntity(): ?PurchaseOrder
    {
        /** @var ?PurchaseOrder */
        return $this->relations->getOne('purchaseOrder');
    }

    /**
     * @todo Rename to `getSalesOrder` when v9.0 is min supported.
     * @internal
     */
    public function getReturnOrderEntity(): ?ReturnOrder
    {
        /** @var ?ReturnOrder */
        return $this->relations->getOne('returnOrder');
    }

    public function getWarehouse(): ?Link
    {
        /** @var ?Link */
        return $this->getValueObject('warehouse');
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

    /**
     * @return OrderItem[]
     */
    public function getReceivedItems(): array
    {
        return array_map(
            fn ($item) => OrderItem::fromRaw($item),
            $this->get(self::ATTR_RECEIVED_ITEM_LIST) ?? []
        );
    }

    /**
     * @param OrderItem[] $items
     */
    public function setReceivedItems(array $items): self
    {
        $rawItems = array_map(
            fn ($item) => (object) [
                'id' => $item->getId(),
                'inventoryNumberId' => $item->getInventoryNumberId(),
                'inventoryNumberName' => $item->getInventoryNumberName(),
                'productId' => $item->getProductId(),
                'productName' => $item->getProductName(),
                'name' => $item->getProductName(),
                'quantity' => $item->getQuantity(),
            ],
            $items
        );

        $this->set(self::ATTR_RECEIVED_ITEM_LIST, $rawItems);

        return $this;
    }

    /**
     * @return string[]
     */
    public function getInventoryNumberIds(): array
    {
        $ids = array_map(
            fn ($item) => $item->getInventoryNumberId(),
            $this->getReceivedItems()
        );

        return array_values(array_filter(
            $ids,
            fn ($item) => $item !== null
        ));
    }

    public function getDateOrdered(): ?Date
    {
        /** @var ?Date */
        return $this->getValueObject('dateOrdered');
    }

    public function getDateReceived(): ?Date
    {
        /** @var ?Date */
        return $this->getValueObject('dateReceived');
    }

    /*public function getTransactionItems(): array
    {
        $items = $this->getItems();

        $transactionItems = [];

        foreach ($items as $item) {
            if ($item->getInventoryNumberType()) {
                continue;
            }

            $transactionItems[] = new OrderItem(
                productId: $item->getProductId(),
                productName: $item->getProductName(),
                inventoryNumberId: $item->getInventoryNumberId(),
                inventoryNumberName: $item->getInventoryNumberName(),
                quantity: $item->getQuantityReceived(),
            );
        }

        foreach ($this->getReceivedItems() as $item) {
            $transactionItems[] = $item;
        }

        return $transactionItems;
    }*/

    /** @noinspection PhpUnused */
    public function loadReceivedItemListField(): void
    {
        $itemParentIdAttribute = lcfirst($this->getEntityType()) . 'Id';

        /** @var iterable<ReceiptOrderReceivedItem> $items */
        $items = $this->entityManager
            ->getRDBRepository(ReceiptOrderReceivedItem::ENTITY_TYPE)
            ->where([$itemParentIdAttribute => $this->getId()])
            ->order('order')
            ->find();

        $mapList = [];

        foreach ($items as $item) {
            $mapList[] = $item->getRawValues();
        }

        $this->set(self::ATTR_RECEIVED_ITEM_LIST, $mapList);

        if (!$this->hasFetched(self::ATTR_RECEIVED_ITEM_LIST)) {
            $this->setFetched(self::ATTR_RECEIVED_ITEM_LIST, $mapList);
        }
    }

    public function setWarehouseId(?string $value): self
    {
        $this->set('warehouseId', $value);

        return $this;
    }
}
