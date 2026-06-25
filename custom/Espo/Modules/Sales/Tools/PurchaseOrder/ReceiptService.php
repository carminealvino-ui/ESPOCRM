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

namespace Espo\Modules\Sales\Tools\PurchaseOrder;

use Espo\Core\Utils\Metadata;
use Espo\Modules\Sales\Entities\Product;
use Espo\Modules\Sales\Entities\PurchaseOrder;
use Espo\Modules\Sales\Entities\PurchaseOrderItem;
use Espo\Modules\Sales\Entities\ReceiptOrder;
use Espo\Modules\Sales\Entities\ReceiptOrderItem;
use Espo\Modules\Sales\Entities\ReturnOrder;
use Espo\Modules\Sales\Entities\ReturnOrderItem;
use Espo\Modules\Sales\Entities\SalesOrderItem;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\ORM\Collection;
use Espo\ORM\EntityManager;
use stdClass;

class ReceiptService
{
    public function __construct(
        private EntityManager $entityManager,
        private Metadata $metadata,
    ) {}

    public function controlReceiptFullyCreated(PurchaseOrder|ReturnOrder $purchaseOrder, bool $noSave = false): void
    {
        $items = $this->getItems($purchaseOrder, $noSave);

        if (count($items) === 0) {
            if (!$purchaseOrder->isReceiptFullyCreated()) {
                return;
            }

            $purchaseOrder->set('isReceiptFullyCreated', false);

            if ($noSave) {
                return;
            }

            $this->entityManager->saveEntity($purchaseOrder);

            return;
        }

        $map1 = [];
        $map2 = [];

        foreach ($items as $item) {
            $quantity = $item->getQuantity();
            $productId = $item->getProduct()?->getId();

            if (!$productId || $item->getProductEntity()?->getItemType() !== Product::ITEM_TYPE_GOODS) {
                continue;
            }

            $map1[$productId] ??= 0.0;
            $map1[$productId] += $quantity;
        }

        $idAttribute = lcfirst($purchaseOrder->getEntityType()) . 'Id';

        $receiptOrders = $this->entityManager
            ->getRDBRepositoryByClass(ReceiptOrder::class)
            ->where([
                $idAttribute => $purchaseOrder->getId(),
                'status!=' => $this->getCanceledStatusList(),
            ])
            ->find();

        foreach ($receiptOrders as $receiptOrder) {
            $receiptItems = $this->entityManager
                ->getRDBRepositoryByClass(ReceiptOrderItem::class)
                ->where(['receiptOrderId' => $receiptOrder->getId()])
                ->find();

            foreach ($receiptItems as $item) {
                /** @var ReceiptOrderItem $item */
                $quantity = $item->getQuantity();
                $productId = $item->getProduct()?->getId();

                if (!$productId) {
                    continue;
                }

                $map2[$productId] ??= 0.0;
                $map2[$productId] += $quantity;
            }
        }

        $isFullyCreated = true;

        foreach ($map1 as $id => $quantity) {
            $receiptQuantity = $map2[$id] ?? 0.0;

            if ($quantity > $receiptQuantity) {
                $isFullyCreated = false;
            }
        }

        if ($purchaseOrder->isReceiptFullyCreated() === $isFullyCreated) {
            return;
        }

        $purchaseOrder->set('isReceiptFullyCreated', $isFullyCreated);

        if ($noSave) {
            return;
        }

        $this->entityManager->saveEntity($purchaseOrder);
    }

    /**
     * @return string[]
     */
    private function getCanceledStatusList(): array
    {
        return $this->metadata->get('scopes.ReceiptOrder.canceledStatusList') ?? [];
    }

    /**
     * @return PurchaseOrderItem[]|ReturnOrderItem[]
     */
    private function getItems(PurchaseOrder|ReturnOrder $purchaseOrder, bool $noSave): array
    {
        $itemEntityType = $purchaseOrder->getItemEntityType();

        if (!$noSave) {
            /** @var Collection<PurchaseOrderItem>|Collection<ReturnOrderItem> $items */
            $items = $this->entityManager
                ->getRDBRepository($purchaseOrder->getItemEntityType())
                ->where([$purchaseOrder->getItemForeignKey() => $purchaseOrder->getId()])
                ->find();

            return iterator_to_array($items);
        }

        $items = [];

        /** @var stdClass[] $rawList */
        $rawList = $purchaseOrder->get(OrderEntity::ATTR_ITEM_LIST) ?? [];

        foreach ($rawList as $rawItem) {
            /** @var PurchaseOrderItem|ReturnOrderItem $item */
            $item = $this->entityManager
                ->getRDBRepository($itemEntityType)
                ->getNew();

            $item->set($rawItem);
            $item->setAsNotNew();

            $items[] = $item;
        }

        return $items;
    }
}
