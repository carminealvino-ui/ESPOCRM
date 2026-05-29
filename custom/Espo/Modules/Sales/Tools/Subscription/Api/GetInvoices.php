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

namespace Espo\Modules\Sales\Tools\Subscription\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Record\EntityProvider;
use Espo\Core\Record\SearchParamsFetcher;
use Espo\Modules\Sales\Entities\Subscription;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Tools\Subscription\InvoiceRecordService;

/**
 * @noinspection PhpUnused
 */
class GetInvoices implements Action
{
    public function __construct(
        private EntityProvider $entityProvider,
        private Acl $acl,
        private SearchParamsFetcher $searchParamsFetcher,
        private InvoiceRecordService $invoiceRecordService,
    ) {}

    public function process(Request $request): Response
    {
        $id = $request->getRouteParam('id') ?? throw new BadRequest();

        $subscription = $this->entityProvider->getByClass(Subscription::class, $id);

        if (!$this->acl->checkScope(Invoice::ENTITY_TYPE)) {
            throw new Forbidden("No access to Invoice scope.");
        }

        $searchParams = $this->searchParamsFetcher->fetch($request);

        $collection = $this->invoiceRecordService->find($subscription, $searchParams);

        return ResponseComposer::json($collection->toApiOutput());
    }
}
