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
use Espo\Modules\Sales\Entities\SubscriptionPeriod;
use Espo\Modules\Sales\Tools\Subscription\CreateInvoice\Data;
use Espo\Modules\Sales\Tools\Subscription\CreateInvoiceForPeriod;
use Espo\Modules\Sales\Tools\Subscription\Exceptions\NotProperStatus;

/**
 * @noinspection PhpUnused
 */
class PostProcessBilling implements Action
{
    public function __construct(
        private EntityProvider $entityProvider,
        private Acl $acl,
        private CreateInvoiceForPeriod $createInvoiceForPeriod,
    ) {}

    public function process(Request $request): Response
    {
        $period = $this->getPeriod($request);

        $data = new Data(
            createPaymentRequest: (bool) $request->getParsedBody()->createPaymentRequest,
            sendPaymentRequest: (bool) $request->getParsedBody()->sendPaymentRequest,
            sendInvoice: (bool) $request->getParsedBody()->sendInvoice,
        );

        try {
            $this->createInvoiceForPeriod->processInTransaction($period, $data);
        } catch (NotProperStatus) {
            throw new Forbidden("Status is not pending.");
        }

        return ResponseComposer::json(true);
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
}
