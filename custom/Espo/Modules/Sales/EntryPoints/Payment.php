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

namespace Espo\Modules\Sales\EntryPoints;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\EntryPoint\EntryPoint;
use Espo\Core\EntryPoint\Traits\NoAuth;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Utils\Client\ActionRenderer;
use Espo\Core\Utils\Client\ActionRenderer\Params as ActionParams;
use Espo\Core\Utils\Language;
use Espo\Modules\Sales\Entities\PaymentRequest;
use Espo\Modules\Sales\Tools\PaymentRequest\PaymentLink\Params;
use Espo\Modules\Sales\Tools\PaymentRequest\PaymentLinkService;
use Espo\ORM\EntityManager;

class Payment implements EntryPoint
{
    use NoAuth;

    public function __construct(
        private PaymentLinkService $service,
        private ActionRenderer $actionRenderer,
        private EntityManager $entityManager,
        private Language $language,
    ) {}

    public function run(Request $request, Response $response): void
    {
        $paymentRequest = $this->getPaymentRequest($request);
        $params = $this->prepareParams($request);

        $data = $this->service->getData($paymentRequest, $params);

        $actionParams = new ActionParams(
            controller: 'modules/sales/controllers/payment-link',
            action: 'show',
            data: $data,
        );

        $actionParams = $actionParams->withPageTitle($this->getPageTitle($paymentRequest));

        $this->actionRenderer->write($response, $actionParams);
    }

    /**
     * @throws BadRequest
     * @throws NotFound
     */
    private function getPaymentRequest(Request $request): PaymentRequest
    {
        $id = $request->getQueryParam('id') ?? throw new BadRequest("No ID.");

        $paymentRequest = $this->entityManager
            ->getRDBRepositoryByClass(PaymentRequest::class)
            ->where(['referenceId' => $id])
            ->findOne();

        if (!$paymentRequest) {
            throw new NotFound();
        }

        return $paymentRequest;
    }

    private function getPageTitle(PaymentRequest $paymentRequest): string
    {
        $title = $this->language->translateLabel(PaymentRequest::ENTITY_TYPE, 'scopeNames');
        $title .= ' · ' . $paymentRequest->getNumber();

        return $title;
    }

    /**
     * @throws BadRequest
     */
    private function prepareParams(Request $request): Params
    {
        $flow = $request->getQueryParam('flow');

        if ($flow !== null && $flow !== Params::FLOW_CHECKOUT_SUCCESS) {
            throw new BadRequest("Bad flow value.");
        }

        return new Params(flow: $flow);
    }
}
