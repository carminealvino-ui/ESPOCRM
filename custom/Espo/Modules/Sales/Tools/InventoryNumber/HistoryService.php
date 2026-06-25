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

namespace Espo\Modules\Sales\Tools\InventoryNumber;

use Espo\Core\Acl;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Field\LinkParent;
use Espo\Core\Utils\Metadata;
use Espo\Modules\Sales\Entities\DeliveryOrder;
use Espo\Modules\Sales\Entities\DeliveryOrderItem;
use Espo\Modules\Sales\Entities\InventoryAdjustment;
use Espo\Modules\Sales\Entities\InventoryAdjustmentItem;
use Espo\Modules\Sales\Entities\InventoryNumber;
use Espo\Modules\Sales\Entities\ReceiptOrder;
use Espo\Modules\Sales\Entities\ReceiptOrderReceivedItem;
use Espo\Modules\Sales\Entities\TransferOrder;
use Espo\Modules\Sales\Entities\TransferOrderItem;
use Espo\Modules\Sales\Tools\InventoryNumber\History\Item;
use Espo\ORM\EntityManager;
use RuntimeException;

class HistoryService
{
    /** @var array<string, ReceiptOrder> */
    private array $receiptOrderMap = [];
    /** @var array<string, TransferOrder> */
    private array $transferOrderMap = [];
    /** @var array<string, DeliveryOrder> */
    private array $deliveryOrderMap = [];

    public function __construct(
        private EntityManager $entityManager,
        private Acl $acl,
        private Metadata $metadata
    ) {}

    /**
     * @param string $id
     * @return Item[]
     * @throws NotFound
     * @throws Forbidden
     */
    public function get(string $id): array
    {
        $number = $this->getInventoryNumber($id);

        $receiptItems = $this->findReceiptOrderItems($number);
        $transferItems = $this->findTransferOrderItems($number);
        $deliveryItems = $this->findDeliveryOrderItems($number);
        $adjustmentItems = $this->findAdjustmentItems($number);

        $items = [];

        foreach ($receiptItems as $orderItem) {
            $order = $this->getReceiptOrder($orderItem);

            $items[] = new Item(
                item: LinkParent::createFromEntity($orderItem)
                    ->withName($orderItem->getProduct()->getName()),
                order: LinkParent::createFromEntity($order)
                    ->withName($order->getNumber()),
                date: $order->getDateReceived() ??
                    $order->getDateOrdered() ??
                    $order->getDateCreatedAt(),
                warehouse: $order->getWarehouse(),
                quantity: $orderItem->getQuantity(),
            );
        }

        foreach ($transferItems as $orderItem) {
            $order = $this->getTransferOrder($orderItem);

            $items[] = new Item(
                item: LinkParent::createFromEntity($orderItem)
                    ->withName($orderItem->getProduct()->getName()),
                order: LinkParent::createFromEntity($order)
                    ->withName($order->getNumber()),
                date: $order->getShippingDate() ??
                    $order->getDateOrdered() ??
                    $order->getDateCreatedAt(),
                warehouse: $order->getFromWarehouse(),
                quantity: - $orderItem->getQuantity(),
            );

            if (!$orderItem->getQuantityReceived()) {
                continue;
            }

            $items[] = new Item(
                item: LinkParent::createFromEntity($orderItem)
                    ->withName($orderItem->getProduct()->getName()),
                order: LinkParent::createFromEntity($order)
                    ->withName($order->getNumber()),
                date: $order->getDeliveryDate() ??
                    $order->getShippingDate() ??
                    $order->getDateOrdered() ??
                    $order->getDateCreatedAt(),
                warehouse: $order->getToWarehouse(),
                quantity: $orderItem->getQuantityReceived(),
            );
        }

        foreach ($deliveryItems as $orderItem) {
            $order = $this->getDeliveryOrder($orderItem);

            $items[] = new Item(
                item: LinkParent::createFromEntity($orderItem)
                    ->withName($orderItem->getProduct()->getName()),
                order: LinkParent::createFromEntity($order)
                    ->withName($order->getNumber()),
                date: $order->getShippingDate() ??
                    $order->getDateOrdered() ??
                    $order->getDateCreatedAt(),
                warehouse: $order->getWarehouse(),
                quantity: - $orderItem->getQuantity(),
            );
        }

        foreach ($adjustmentItems as $orderItem) {
            $order = $this->getAdjustment($orderItem);

            $items[] = new Item(
                item: LinkParent::createFromEntity($orderItem)
                    ->withName($orderItem->getProduct()->getName()),
                order: LinkParent::createFromEntity($order)
                    ->withName($order->getNumber()),
                date: $order->getDate() ??
                    $order->getDateCreatedAt(),
                warehouse: $order->getWarehouse(),
                quantity: $orderItem->getQuantity(),
            );
        }

        usort($items, function (Item $item1, Item $item2) {
            if ($item1->getDate()->isEqualTo($item2->getDate())) {
                if ($item1->getQuantity() >= 0 && $item2->getQuantity() < 0) {
                    return 1;
                }

                if ($item1->getQuantity() < 0 && $item2->getQuantity() >= 0) {
                    return -1;
                }

                return 0;
            }

            return $item1->getDate()->isGreaterThan($item2->getDate()) ?
                1 : -1;
        });

        return $items;
    }

    /**
     * @return ReceiptOrderReceivedItem[]
     */
    private function findReceiptOrderItems(InventoryNumber $number): array
    {
        $items = $this->entityManager
            ->getRDBRepositoryByClass(ReceiptOrderReceivedItem::class)
            ->join('receiptOrder')
            ->where([
                'receiptOrder.status' => $this->metadata->get('scopes.ReceiptOrder.doneStatusList') ?? [],
                'inventoryNumberId' => $number->getId(),
            ])
            ->find();

        return iterator_to_array($items);
    }

    /**
     * @return DeliveryOrderItem[]
     */
    private function findDeliveryOrderItems(InventoryNumber $number): array
    {
        $items = $this->entityManager
            ->getRDBRepositoryByClass(DeliveryOrderItem::class)
            ->join('deliveryOrder')
            ->where([
                'deliveryOrder.status!=' => array_merge(
                    $this->metadata->get('scopes.ReceiptOrder.canceledStatusList') ?? [],
                    $this->metadata->get('scopes.ReceiptOrder.softReserveStatusList') ?? [],
                    $this->metadata->get('scopes.ReceiptOrder.reserveStatusList') ?? [],
                ),
                'inventoryNumberId' => $number->getId(),
            ])
            ->find();

        return iterator_to_array($items);
    }

    /**
     * @return TransferOrderItem[]
     */
    private function findTransferOrderItems(InventoryNumber $number): array
    {
        $items = $this->entityManager
            ->getRDBRepositoryByClass(TransferOrderItem::class)
            ->join('transferOrder')
            ->where([
                'transferOrder.status!=' => array_merge(
                    $this->metadata->get('scopes.TransferOrder.canceledStatusList') ?? [],
                    $this->metadata->get('scopes.TransferOrder.softReserveStatusList') ?? [],
                    $this->metadata->get('scopes.TransferOrder.reserveStatusList') ?? [],
                ),
                'inventoryNumberId' => $number->getId(),
            ])
            ->find();

        return iterator_to_array($items);
    }

    /**
     * @return array<InventoryAdjustmentItem>
     */
    private function findAdjustmentItems(InventoryNumber $number): array
    {
        $items = $this->entityManager
            ->getRDBRepositoryByClass(InventoryAdjustmentItem::class)
            ->join('inventoryAdjustment')
            ->where([
                'inventoryAdjustment.status=' => InventoryAdjustment::STATUS_COMPLETED,
                'inventoryNumberId' => $number->getId(),
            ])
            ->find();

        return iterator_to_array($items);
    }

    /**
     * @throws Forbidden
     * @throws NotFound
     */
    private function getInventoryNumber(string $id): InventoryNumber
    {
        $inventoryNumber = $this->entityManager
            ->getRDBRepositoryByClass(InventoryNumber::class)
            ->getById($id);

        if (!$inventoryNumber) {
            throw new NotFound();
        }

        if (!$this->acl->checkEntityRead($inventoryNumber)) {
            throw new Forbidden();
        }

        return $inventoryNumber;
    }

    private function getReceiptOrder(ReceiptOrderReceivedItem $item): ReceiptOrder
    {
        $id = $item->getReceiptOrderId();

        if (!$id) {
            throw new RuntimeException();
        }

        if (isset($this->receiptOrderMap[$id])) {
            return $this->receiptOrderMap[$id];
        }

        $order = $this->entityManager
            ->getRDBRepositoryByClass(ReceiptOrder::class)
            ->getById($id);

        if (!$order) {
            throw new RuntimeException();
        }

        $this->receiptOrderMap[$id] = $order;

        return $order;
    }

    private function getTransferOrder(TransferOrderItem $item): TransferOrder
    {
        $id = $item->getTransferOrderId();

        if (!$id) {
            throw new RuntimeException();
        }

        if (isset($this->transferOrderMap[$id])) {
            return $this->transferOrderMap[$id];
        }

        $order = $this->entityManager
            ->getRDBRepositoryByClass(TransferOrder::class)
            ->getById($id);

        if (!$order) {
            throw new RuntimeException();
        }

        $this->transferOrderMap[$id] = $order;

        return $order;
    }

    private function getDeliveryOrder(DeliveryOrderItem $item): DeliveryOrder
    {
        $id = $item->getDeliveryOrderId();

        if (!$id) {
            throw new RuntimeException();
        }

        if (isset($this->deliveryOrderMap[$id])) {
            return $this->deliveryOrderMap[$id];
        }

        $order = $this->entityManager
            ->getRDBRepositoryByClass(DeliveryOrder::class)
            ->getById($id);

        if (!$order) {
            throw new RuntimeException();
        }

        $this->deliveryOrderMap[$id] = $order;

        return $order;
    }

    private function getAdjustment(InventoryAdjustmentItem $item): InventoryAdjustment
    {
        $id = $item->getInventoryAdjustmentId();

        if (!$id) {
            throw new RuntimeException();
        }

        $order = $this->entityManager
            ->getRDBRepositoryByClass(InventoryAdjustment::class)
            ->getById($id);

        if (!$order) {
            throw new RuntimeException();
        }

        return $order;
    }
}
