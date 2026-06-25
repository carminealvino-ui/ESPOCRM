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

namespace Espo\Modules\Sales\Tools\PaymentRequest\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Record\EntityProvider;
use Espo\Entities\Email;
use Espo\Modules\Sales\Entities\PaymentRequest;
use Espo\Modules\Sales\Tools\PaymentRequest\PrepareEmailAttributesService;

/**
 * @noinspection PhpUnused
 */
class PostPrepareEmailAttributes implements Action
{
    public function __construct(
        private PrepareEmailAttributesService $service,
        private EntityProvider $entityProvider,
        private Acl $acl,
    ) {}

    public function process(Request $request): Response
    {
        $this->checkAccess();

        $paymentRequest = $this->getEntity($request);

        $attributes = $this->service->prepare($paymentRequest);

        return ResponseComposer::json($attributes);
    }

    /**
     * @throws BadRequest
     * @throws Forbidden
     * @throws NotFound
     */
    private function getEntity(Request $request): PaymentRequest
    {
        $id = $request->getRouteParam('id') ?? throw new BadRequest();

        $paymentRequest = $this->entityProvider->getByClass(PaymentRequest::class, $id);

        if (!$this->acl->checkEntityRead($paymentRequest)) {
            throw new Forbidden();
        }

        return $paymentRequest;
    }

    /**
     * @throws Forbidden
     */
    private function checkAccess(): void
    {
        if (!$this->acl->checkScope(Email::ENTITY_TYPE, Acl\Table::ACTION_CREATE)) {
            throw new Forbidden("No Email create access.");
        }
    }
}
