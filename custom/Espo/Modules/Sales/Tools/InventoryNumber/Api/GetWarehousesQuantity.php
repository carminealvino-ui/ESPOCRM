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

namespace Espo\Modules\Sales\Tools\InventoryNumber\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Record\SearchParamsFetcher;
use Espo\Modules\Sales\Entities\InventoryNumber;
use Espo\Modules\Sales\Entities\Warehouse;
use Espo\Modules\Sales\Tools\Inventory\Availability\Params;
use Espo\Modules\Sales\Tools\Inventory\AvailabilityDataProvider;
use Espo\Modules\Sales\Tools\Sales\ConfigDataProvider;
use Espo\ORM\EntityManager;

/**
 * @noinspection PhpUnused
 */
class GetWarehousesQuantity implements Action
{
    public function __construct(
        private Acl $acl,
        private EntityManager $entityManager,
        private AvailabilityDataProvider $availabilityDataProvider,
        private ConfigDataProvider $configDataProvider,
        private SearchParamsFetcher $searchParamsFetcher,
    ) {}

    public function process(Request $request): Response
    {
        $id = $request->getRouteParam('id');

        if (!$id) {
            throw new BadRequest();
        }

        $number = $this->entityManager->getEntityById(InventoryNumber::ENTITY_TYPE, $id);

        if (!$number) {
            throw new NotFound();
        }

        if (
            !$this->acl->checkEntityRead($number) ||
            !$this->acl->checkScope(Warehouse::ENTITY_TYPE) ||
            !$this->configDataProvider->isWarehousesEnabled()
        ) {
            throw new Forbidden();
        }

        $searchParams = $this->searchParamsFetcher->fetch($request);

        $params = new Params(stocked: $searchParams->getPrimaryFilter() === 'stocked');

        $dataList = $this->availabilityDataProvider->getWarehousesForNumber($number->getId(), $params);

        $list = array_map(
            function ($data) {
                return (object) [
                    'id' => $data->getId(),
                    'name' => $data->getName(),
                    'quantityOnHand' => $data->getQuantity(),
                    'quantityReserved' => $data->getQuantityReserved(),
                ];
            },
            $dataList
        );

        return ResponseComposer::json(['list' => $list]);
    }
}
