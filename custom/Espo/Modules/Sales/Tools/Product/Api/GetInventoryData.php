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
use Espo\Core\Acl\Table;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\Sales\Entities\DeliveryOrder as DeliveryOrderEntity;
use Espo\Modules\Sales\Entities\SalesOrder as SalesOrderEntity;
use Espo\Modules\Sales\Entities\TransferOrder as TransferOrderEntity;
use Espo\Modules\Sales\Tools\Inventory\AvailabilityDataProvider;
use Espo\Modules\Sales\Tools\Sales\ConfigDataProvider;

/**
 * @noinspection PhpUnused
 */
class GetInventoryData implements Action
{
    private const LIMIT = 500;

    public function __construct(
        private Acl $acl,
        private ConfigDataProvider $configDataProvider,
        private AvailabilityDataProvider $availabilityDataProvider,
    ) {}

    public function process(Request $request): Response
    {
        if (!$this->configDataProvider->isInventoryTransactionsEnabled()) {
            throw new Forbidden();
        }

        if (
            !$this->acl->checkScope(DeliveryOrderEntity::ENTITY_TYPE, Table::ACTION_CREATE) &&
            !$this->acl->checkScope(TransferOrderEntity::ENTITY_TYPE, Table::ACTION_CREATE) &&
            !$this->acl->checkScope(SalesOrderEntity::ENTITY_TYPE, Table::ACTION_CREATE)
        ) {
            throw new Forbidden();
        }

        $productIds = $request->getQueryParam('productIds');
        $excludeId = $request->getQueryParam('excludeId');
        $excludeType = $request->getQueryParam('excludeType');

        if (!is_string($productIds)) {
            throw new BadRequest();
        }

        $productIds = explode(',', $productIds);

        if (count($productIds) > self::LIMIT) {
            throw new BadRequest("Too many products.");
        }

        $items = $this->availabilityDataProvider->getForProducts($productIds, $excludeType, $excludeId);

        $result = [];

        foreach ($items as $item) {
            $result[] = (object) [
                'id' => $item->getId(),
                'name' => $item->getName(),
                'quantity' => $item->getQuantity(),
                'quantityOnHand' => $item->getQuantityOnHand(),
                'warehouses' => array_map(function ($warehouse) {
                    return (object) [
                        'id' => $warehouse->getId(),
                        'quantity' => $warehouse->getQuantity(),
                        'name' => $warehouse->getName(),
                        'quantityOnHand' => $warehouse->getQuantityOnHand(),
                    ];
                }, $item->getWarehouseDataList()),
            ];
        }

        return ResponseComposer::json($result);
    }
}
