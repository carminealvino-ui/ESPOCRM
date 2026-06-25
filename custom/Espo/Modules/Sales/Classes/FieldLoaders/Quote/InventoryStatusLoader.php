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
use Espo\Modules\Sales\Entities\TransferOrder;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\ORM\Entity;

/**
 * @noinspection PhpUnused
 * @implements Loader<OrderEntity>
 */
class InventoryStatusLoader implements Loader
{

    public function process(Entity $entity, Params $params): void
    {
        if (
            !$entity->has('inventoryData') ||
            !is_object($entity->get('inventoryData'))
        ) {
            /** @noinspection PhpRedundantOptionalArgumentInspection */
            $entity->set('inventoryStatus', null);

            return;
        }

        /**
         * @var array<string, object{
         *     quantity: float,
         *     inventoryNumberQuantity?: float,
         *     onHandQuantity?: float,
         * }> $data
         */
        $data = get_object_vars($entity->get('inventoryData'));

        if ($data === []) {
            /** @noinspection PhpRedundantOptionalArgumentInspection */
            $entity->set('inventoryStatus', null);

            return;
        }

        $status = OrderEntity::INVENTORY_STATUS_AVAILABLE;

        foreach ($entity->getItems() as $item) {
            $productId = $item->getProductId();
            $quantity = $item->getQuantity();
            $id = $item->getId();
            $inventoryNumberId = $item->getInventoryNumberId();

            if (!$productId) {
                continue;
            }

            if (!array_key_exists($id, $data) || !is_object($data[$id])) {
                continue;
            }

            $inventoryQuantity = $data[$id]->quantity;

            if (
                (
                    $entity instanceof DeliveryOrder ||
                    $entity instanceof TransferOrder
                ) &&
                $inventoryNumberId
            ) {
                $inventoryNumberQuantity = $data[$id]->inventoryNumberQuantity ?? 0.0;

                if ($quantity > $inventoryNumberQuantity) {
                    $status = OrderEntity::INVENTORY_STATUS_NOT_AVAILABLE;

                    break;
                }
            }

            $onHandQuantity = $data[$id]->onHandQuantity ?? null;

            if ($onHandQuantity !== null) {
                if ($quantity > $onHandQuantity) {
                    $status = OrderEntity::INVENTORY_STATUS_NOT_AVAILABLE;

                    break;
                }

                if ($quantity > $inventoryQuantity) {
                    $status = OrderEntity::INVENTORY_STATUS_ON_HAND;

                    break;
                }
            }

            if ($quantity > $inventoryQuantity) {
                $status = OrderEntity::INVENTORY_STATUS_NOT_AVAILABLE;

                break;
            }
        }

        $entity->set('inventoryStatus', $status);
    }
}
