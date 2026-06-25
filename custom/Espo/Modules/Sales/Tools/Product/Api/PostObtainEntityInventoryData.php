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

namespace Espo\Modules\Sales\Tools\Product\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Modules\Sales\Entities\DeliveryOrder;
use Espo\Modules\Sales\Entities\InventoryNumber;
use Espo\Modules\Sales\Entities\Product;
use Espo\Modules\Sales\Entities\Quote;
use Espo\Modules\Sales\Entities\SalesOrder;
use Espo\Modules\Sales\Entities\TransferOrder;
use Espo\Modules\Sales\Entities\Warehouse;
use Espo\Modules\Sales\Tools\Inventory\QuantityMapsProvider;
use Espo\Modules\Sales\Tools\Sales\ConfigDataProvider;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\ORM\EntityManager;
use stdClass;

/**
 * @noinspection PhpUnused
 */
class PostObtainEntityInventoryData implements Action
{
    public function __construct(
        private Acl $acl,
        private EntityManager $entityManager,
        private QuantityMapsProvider $quantityMapsProvider,
        private ConfigDataProvider $configDataProvider,
    ) {}

    public function process(Request $request): Response
    {
        if (!$this->acl->checkScope(Product::ENTITY_TYPE)) {
            throw new Forbidden("No access to 'Product' scope.");
        }

        $entityType = $request->getParsedBody()->entityType ?? null;
        $id = $request->getParsedBody()->entityId ?? null;

        if (!is_string($entityType)) {
            throw new BadRequest("No entityType.");
        }

        if (!is_string($id) && $id !== null) {
            throw new BadRequest("Bad entityId.");
        }

        if (!$this->acl->checkScope($entityType, Acl\Table::ACTION_EDIT)) {
            throw new Forbidden("No edit access to '$entityType' scope.");
        }

        $itemList = $this->getItemList($request);

        if ($id) {
            $order = $this->entityManager->getEntityById($entityType, $id);

            if (!$order) {
                throw new NotFound();
            }

            if (!$this->acl->checkEntityEdit($order)) {
                throw new Forbidden("No edit access to entity.");
            }
        } else {
            $order = $this->entityManager->getNewEntity($entityType);
        }

        if (
            !$order instanceof SalesOrder &&
            !$order instanceof Quote &&
            !$order instanceof DeliveryOrder &&
            !$order instanceof TransferOrder
        ) {
            throw new Forbidden("Not supported entity type.");
        }

        if ($order instanceof DeliveryOrder || $order instanceof TransferOrder) {
            $this->setWarehouse($request, $order);
        }

        $order->set(OrderEntity::ATTR_ITEM_LIST, $itemList);

        $maps = $this->quantityMapsProvider->get($order);

        return ResponseComposer::json([
            'inventoryQuantityMaps' => $maps->toRaw(),
        ]);
    }

    /**
     * @return (stdClass & object{id: string, productId: string, inventoryNumberId?: string})[]
     * @throws BadRequest
     */
    private function getItemList(Request $request): array
    {
        $items = $request->getParsedBody()->itemList ?? null;

        if (!is_array($items)) {
            throw new BadRequest("No items.");
        }

        $output = [];

        foreach ($items as $item) {
            if (!$item instanceof stdClass) {
                throw new BadRequest("Bad item.");
            }

            $id = $item->id ?? null;
            $productId = $item->productId ?? null;
            //$quantity = $item->quantity ?? null;

            if (!is_string($id)) {
                continue;
            }

            if (!is_string($productId)) {
                continue;
            }

            /*if (!is_int($quantity) && !is_float($quantity)) {
                continue;
            }*/

            $product = $this->entityManager->getRDBRepositoryByClass(Product::class)->getById($productId);

            if (!$product) {
                continue;
            }

            if (!$this->acl->checkEntityRead($product)) {
                continue;
            }

            $inventoryNumberId = $item->inventoryNumberId ?? null;

            if ($inventoryNumberId !== null && !is_string($inventoryNumberId)) {
                throw new BadRequest("Bad inventoryNumberId.");
            }

            $number = null;

            if ($inventoryNumberId) {
                $number = $this->entityManager->getRDBRepositoryByClass(InventoryNumber::class)
                    ->getById($inventoryNumberId);

                if (!$number) {
                    continue;
                }

                if (!$this->acl->checkEntityRead($number)) {
                    continue;
                }
            }

            $output[] = (object) [
                'id' => $id,
                'productId' => $productId,
                'inventoryNumberId' => $inventoryNumberId,
                'inventoryNumberType' => $number?->getType(),
                'quantity' => 1.0,
            ];
        }

        return $output;
    }

    /**
     * @throws BadRequest
     * @throws Forbidden
     * @throws NotFound
     */
    private function setWarehouse(Request $request, DeliveryOrder|TransferOrder $order): void
    {
        if (!$this->configDataProvider->isWarehousesEnabled()) {
            return;
        }

        $warehouseId = $request->getParsedBody()->warehouseId ?? null;

        if (!is_string($warehouseId)) {
            throw new BadRequest("No warehouseId.");
        }

        $warehouse = $this->entityManager->getRDBRepositoryByClass(Warehouse::class)->getById($warehouseId);

        if (!$warehouse) {
            throw new NotFound("Warehouse not found.");
        }

        if (!$this->acl->checkEntityRead($warehouse)) {
            throw new Forbidden("No access to warehouse");
        }

        if ($order instanceof TransferOrder) {
            $order->setFromWarehouseId($warehouse->getId());
        } else {
            $order->setWarehouseId($warehouse->getId());
        }
    }
}
