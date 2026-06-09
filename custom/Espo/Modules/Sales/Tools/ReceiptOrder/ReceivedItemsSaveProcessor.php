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

namespace Espo\Modules\Sales\Tools\ReceiptOrder;

use Espo\Modules\Sales\Entities\ReceiptOrder;
use Espo\Modules\Sales\Entities\ReceiptOrderReceivedItem;
use Espo\Modules\Sales\Tools\Sales\OrderItem;
use Espo\ORM\Collection;
use Espo\ORM\EntityManager;

class ReceivedItemsSaveProcessor
{
    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function process(ReceiptOrder $receiptOrder, bool $isNew): void
    {
        if (!$receiptOrder->has(ReceiptOrder::ATTR_RECEIVED_ITEM_LIST)) {
            return;
        }

        $toCreateList = [];
        $toUpdateList = [];
        $toRemoveList = [];

        if (!$isNew) {
            [$prevItemCollection, $toRemoveList] = $this->processPrevious($receiptOrder);
        }

        $index = 0;

        foreach ($receiptOrder->getReceivedItems() as $item) {
            $index ++;

            $prevItem = !$isNew && $item->getId() ?
                $this->findInCollectionById($prevItemCollection, $item->getId()) :
                null;

            $isChanged =
                $prevItem &&
                (
                    $item->getQuantity() !== $prevItem->getQuantity() ||
                    $item->getProductId() !== $prevItem->getProduct()->getId() ||
                    $item->getInventoryNumberId() !== $prevItem->getInventoryNumber()->getId() ||
                    $index !== $prevItem->getOrder()
                );

            if ($isChanged) {
                $this->setItem($prevItem, $item, $index);

                $toUpdateList[] = $prevItem;
            }

            if ($prevItem) {
                continue;
            }

            /** @var ReceiptOrderReceivedItem $newItem */
            $newItem = $this->entityManager->getNewEntity(ReceiptOrderReceivedItem::ENTITY_TYPE);

            $this->setItem($newItem, $item, $index);

            $newItem->set('receiptOrderId', $receiptOrder->getId());

            $toCreateList[] = $newItem;
        }

        foreach ($toRemoveList as $item) {
            $this->entityManager->removeEntity($item);
        }

        foreach ($toUpdateList as $item) {
            $this->entityManager->saveEntity($item);
        }

        foreach ($toCreateList as $item) {
            $this->entityManager->saveEntity($item);
        }

        $receiptOrder->loadReceivedItemListField();
    }

    private function setItem(ReceiptOrderReceivedItem $entity, OrderItem $item, int $index): void
    {
        $entity->set('quantity', $item->getQuantity());
        $entity->set('productId', $item->getProductId());
        $entity->set('productName', $item->getProductName());
        $entity->set('inventoryNumberId', $item->getInventoryNumberId());
        $entity->set('inventoryNumberName', $item->getInventoryNumberName());
        $entity->set('order', $index);
    }

    /**
     * @param Collection<ReceiptOrderReceivedItem> $collection
     * @param string $id
     */
    private function findInCollectionById(Collection $collection, string $id): ?ReceiptOrderReceivedItem
    {
        foreach ($collection as $entity) {
            if ($entity->getId() === $id) {
                return $entity;
            }
        }

        return null;
    }

    /**
     * @param ReceiptOrder $receiptOrder
     * @return array{Collection<ReceiptOrderReceivedItem>, ReceiptOrderReceivedItem[]}
     */
    public function processPrevious(ReceiptOrder $receiptOrder): array
    {
        $toRemoveList = [];

        $prevItemCollection = $this->entityManager
            ->getRDBRepositoryByClass(ReceiptOrderReceivedItem::class)
            ->where(['receiptOrderId' => $receiptOrder->getId()])
            ->order('order')
            ->find();

        $mapList = [];

        foreach ($prevItemCollection as $prevItem) {
            /** @var ReceiptOrderReceivedItem $prevItem */

            $mapList[] = $prevItem->getRawValues();

            $exists = false;

            foreach ($receiptOrder->getReceivedItems() as $item) {
                if ($prevItem->getId() === $item->getId()) {
                    $exists = true;

                    break;
                }
            }

            if (!$exists) {
                $toRemoveList[] = $prevItem;
            }
        }

        $receiptOrder->setFetched(ReceiptOrder::ATTR_RECEIVED_ITEM_LIST, $mapList);

        return [$prevItemCollection, $toRemoveList];
    }
}
