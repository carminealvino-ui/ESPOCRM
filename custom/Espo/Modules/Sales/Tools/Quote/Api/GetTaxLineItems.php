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

namespace Espo\Modules\Sales\Tools\Quote\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Record\EntityProvider;
use Espo\Core\Record\SearchParamsFetcher;
use Espo\Modules\Sales\Entities\Quote;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\Modules\Sales\Tools\TaxLineItem\LineItemsRecordService;

/**
 * @noinspection PhpUnused
 */
class GetTaxLineItems implements Action
{
    public function __construct(
        private SearchParamsFetcher $searchParamsFetcher,
        private EntityProvider $entityProvider,
        private Acl $acl,
        private LineItemsRecordService $service,
    ) {}

    public function process(Request $request): Response
    {
        $order = $this->getOrder($request);
        $searchParams = $this->searchParamsFetcher->fetch($request);

        $this->checkFieldAccess($order);

        $collection = $this->service->find($order, $searchParams);

        return ResponseComposer::json(
            $collection->toApiOutput()
        );
    }

    /**
     * @throws BadRequest
     * @throws Forbidden
     * @throws NotFound
     */
    private function getOrder(Request $request): OrderEntity
    {
        $entityType = $request->getRouteParam('entityType') ?? throw new BadRequest();
        $id = $request->getRouteParam('id') ?? throw new BadRequest();

        $order = $this->entityProvider->get($entityType, $id);

        if (!$this->acl->checkEntityRead($order)) {
            throw new Forbidden("No read access.");
        }

        if (!$order instanceof OrderEntity) {
            throw new Forbidden();
        }

        return $order;
    }

    /**
     * @throws Forbidden
     */
    private function checkFieldAccess(OrderEntity $order): void
    {
        if (!$this->acl->checkField($order->getEntityType(), Quote::FIELD_TAX_TOTALS)) {
            throw new Forbidden("No access to the Tax Totals field");
        }
    }
}
