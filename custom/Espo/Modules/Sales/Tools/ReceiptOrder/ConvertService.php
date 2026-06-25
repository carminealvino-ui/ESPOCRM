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

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Utils\Metadata;
use Espo\Modules\Sales\Entities\QuoteItem;
use Espo\Modules\Sales\Entities\ReceiptOrder;
use Espo\Modules\Sales\Entities\ReceiptOrderItem;
use Espo\Modules\Sales\Tools\Quote\ConvertService as GeneralConvertService;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\ORM\EntityManager;

class ConvertService
{
    public function __construct(
        private GeneralConvertService $convertService,
        private EntityManager $entityManager,
        private Metadata $metadata
    ) {}

    /**
     * @return array<string, mixed>
     * @throws Forbidden
     * @throws NotFound
     */
    public function getAttributes(string $sourceType, string $sourceId): array
    {
        $attributes = $this->convertService->getAttributes(ReceiptOrder::ENTITY_TYPE, $sourceType, $sourceId);

        $linkAttribute = lcfirst($sourceType) . 'Id';

        $receipts = $this->entityManager
            ->getRDBRepository(ReceiptOrder::ENTITY_TYPE)
            ->where([
                $linkAttribute => $sourceId,
                'status!=' => $this->getCanceledStatusList(),
            ])
            ->find();

        if (iterator_count($receipts) === 0) {
            return $attributes;
        }

        $map = [];

        /** @var ReceiptOrderItem[] $items */
        $items = [];

        foreach ($attributes[OrderEntity::ATTR_ITEM_LIST] ?? [] as $rawItem) {
            /** @var ReceiptOrderItem $item */
            $item = $this->entityManager->getNewEntity(ReceiptOrderItem::ENTITY_TYPE);
            $item->set($rawItem);

            if (!$item->getProduct()) {
                continue;
            }

            $productId = $item->getProduct()->getId();

            $map[$productId] ??= 0.0;
            $map[$productId] += $item->getQuantity();

            $items[] = $item;
        }

        foreach ($receipts as $receipt) {
            $receiptItems = $this->entityManager
                ->getRDBRepository(ReceiptOrder::ENTITY_TYPE)
                ->getRelation($receipt, 'items')
                ->find();

            foreach ($receiptItems as $item) {
                /** @var ReceiptOrderItem $item */

                if (!$item->getProduct()) {
                    continue;
                }

                $productId = $item->getProduct()->getId();

                if (!isset($map[$productId])) {
                    continue;
                }

                $map[$productId] -= $item->getQuantity();
            }
        }

        /** @var QuoteItem[] $newItems */
        $newItems = [];

        foreach ($items as $item) {
            $productId = $item->getProduct()?->getId();

            if (!$productId) {
                continue;
            }

            foreach ($newItems as $newItem) {
                if ($newItem->getProduct()?->getId() === $productId) {
                    continue 2;
                }
            }

            $newItems[] = $item;
        }

        $items = $newItems;

        $rawItems = [];

        foreach ($items as $item) {
            $productId = $item->getProduct()?->getId();

            if (!$productId) {
                continue;
            }

            $quantity = $map[$productId] ?? 0.0;

            if ($quantity === 0.0) {
                continue;
            }

            $rawItem = get_object_vars($item->getValueMap());

            $rawItem['quantity'] = $quantity;

            $rawItems[] = $rawItem;
        }

        foreach ($rawItems as &$item) {
            $item['quantityReceived'] = null;
        }

        $attributes[OrderEntity::ATTR_ITEM_LIST] = $rawItems;

        return $attributes;
    }

    /**
     * @return string[]
     */
    private function getCanceledStatusList(): array
    {
        return $this->metadata->get('scopes.ReceiptOrder.canceledStatusList') ?? [];
    }
}
