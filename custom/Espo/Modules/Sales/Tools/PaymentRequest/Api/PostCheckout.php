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

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\NotFound;
use Espo\Modules\Sales\Entities\PaymentRequest;
use Espo\Modules\Sales\Tools\PaymentRequest\CheckoutService;
use Espo\ORM\EntityManager;

/**
 * @noinspection PhpUnused
 */
class PostCheckout implements Action
{
    public function __construct(
        private EntityManager $entityManager,
        private CheckoutService $service,
    ) {}

    /**
     * @inheritDoc
     */
    public function process(Request $request): Response
    {
        $paymentRequest = $this->getPaymentRequest($request);

        $result = $this->service->process($paymentRequest);

        return ResponseComposer::json([
            'redirectUrl' => $result->getRedirectUrl(),
        ]);
    }

    /**
     * @throws BadRequest
     * @throws NotFound
     */
    private function getPaymentRequest(Request $request): PaymentRequest
    {
        $id = $request->getRouteParam('id') ?? throw new BadRequest();

        $paymentRequest = $this->entityManager
            ->getRDBRepositoryByClass(PaymentRequest::class)
            ->where(['referenceId' => $id])
            ->findOne();

        if (!$paymentRequest) {
            throw new NotFound();
        }

        return $paymentRequest;
    }
}
