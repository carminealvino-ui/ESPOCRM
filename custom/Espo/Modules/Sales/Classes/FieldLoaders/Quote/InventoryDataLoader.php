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

namespace Espo\Modules\Sales\Classes\FieldLoaders\Quote;

use Espo\Core\FieldProcessing\Loader;
use Espo\Core\FieldProcessing\Loader\Params;
use Espo\Modules\Sales\Entities\DeliveryOrder;
use Espo\Modules\Sales\Entities\Quote;
use Espo\Modules\Sales\Entities\SalesOrder;
use Espo\Modules\Sales\Entities\TransferOrder;
use Espo\Modules\Sales\Tools\Inventory\QuantityMapsProvider;
use Espo\Modules\Sales\Tools\Sales\ConfigDataProvider;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\ORM\Entity;

/**
 * @implements Loader<DeliveryOrder|TransferOrder|SalesOrder|Quote>
 * @noinspection PhpUnused
 */
class InventoryDataLoader implements Loader
{
    public function __construct(
        private ConfigDataProvider $configDataProvider,
        private QuantityMapsProvider $quantityMapsProvider,
    ) {}

    public function process(Entity $entity, Params $params): void
    {
        if (!$this->configDataProvider->isInventoryTransactionsEnabled()) {
            return;
        }

        $maps = $this->quantityMapsProvider->get($entity);

        $entity->set('inventoryQuantityMaps', $maps->toRaw());

        if ($maps->isEmpty()) {
            $entity->set('inventoryData', (object) []);

            return;
        }

        $quantityMap = $maps->quantity;
        $onHandQuantityMap = $maps->onHand;
        $totalQuantityMap = $maps->total;
        $inventoryQuantityMap = $maps->inventory;

        $data = (object) [];

        /**
         * @var object{
         *     productId?: string,
         *     quantity?: float,
         *     id: string,
         *     inventoryNumberId?: string,
         *     isInventory?: bool,
         * }[] $itemList
         */
        $itemList = $entity->get(OrderEntity::ATTR_ITEM_LIST) ?? [];

        foreach ($itemList as $item) {
            $productId = $item->productId ?? null;
            $quantity = $item->quantity ?? 0.0;
            $id = $item->id ?? null;
            $inventoryNumberId = $item->inventoryNumberId ?? null;
            $isInventory = $item->isInventory ?? false;

            if (
                !$productId ||
                !$isInventory ||
                !array_key_exists($productId, $quantityMap) ||
                !$id
            ) {
                continue;
            }

            $obj = (object) [
                'quantity' => $quantityMap[$productId],
            ];

            $quantityMap[$productId] -= $quantity;

            $onHandQuantity = $onHandQuantityMap[$productId] ?? null;
            $totalQuantity = $totalQuantityMap[$productId] ?? null;

            if ($totalQuantity !== null) {
                $totalQuantityMap[$productId] -= $quantity;

                $obj->totalQuantity = $totalQuantity;
                $obj->quantity = min($obj->quantity, $obj->totalQuantity);
            }

            if ($inventoryNumberId && array_key_exists($inventoryNumberId, $inventoryQuantityMap)) {
                $obj->inventoryNumberQuantity = $inventoryQuantityMap[$inventoryNumberId];
            }

            if ($onHandQuantity !== null) {
                $obj->onHandQuantity = $onHandQuantity;
            }

            $data->$id = $obj;
        }

        $entity->set('inventoryData', $data);
    }
}
