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

namespace Espo\Modules\Sales\Tools\SubscriptionPeriod\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Record\EntityProvider;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Entities\SubscriptionPeriod;
use Espo\Modules\Sales\Tools\Subscription\InvoiceAttributesPreparator;

/**
 * @noinspection PhpUnused
 */
class PostPrepareInvoiceAttributes implements Action
{
    public function __construct(
        private EntityProvider $entityProvider,
        private Acl $acl,
        private InvoiceAttributesPreparator $invoiceAttributesPreparator,
    ) {}

    public function process(Request $request): Response
    {
        $period = $this->getPeriod($request);

        $this->checkAccess();

        $attributes = $this->invoiceAttributesPreparator->prepare($period);

        return ResponseComposer::json($attributes);
    }

    /**
     * @throws BadRequest
     * @throws Forbidden
     * @throws NotFound
     */
    private function getPeriod(Request $request): SubscriptionPeriod
    {
        $id = $request->getRouteParam('id') ?? throw new BadRequest();

        $period = $this->entityProvider->getByClass(SubscriptionPeriod::class, $id);

        if (!$this->acl->checkEntityEdit($period)) {
            throw new Forbidden("No edit access.");
        }

        return $period;
    }

    /**
     * @throws Forbidden
     */
    private function checkAccess(): void
    {
        if (!$this->acl->checkScope(Invoice::ENTITY_TYPE, Acl\Table::ACTION_CREATE)) {
            throw new Forbidden("No Invoice create access.");
        }
    }
}
