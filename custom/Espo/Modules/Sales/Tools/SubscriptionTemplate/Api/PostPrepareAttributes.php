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

namespace Espo\Modules\Sales\Tools\SubscriptionTemplate\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Field\Date;
use Espo\Core\Record\EntityProvider;
use Espo\Modules\Sales\Entities\SubscriptionBillingPlan;
use Espo\Modules\Sales\Entities\SubscriptionTemplate;
use Espo\Modules\Sales\Tools\SubscriptionTemplate\Prepare\PrepareData;
use Espo\Modules\Sales\Tools\SubscriptionTemplate\PrepareService;
use Exception;

/**
 * @noinspection PhpUnused
 */
class PostPrepareAttributes implements Action
{
    public function __construct(
        private PrepareService $prepareService,
        private EntityProvider $entityProvider,
    ) {}

    public function process(Request $request): Response
    {
        $template = $this->getTemplate($request);

        $data = new PrepareData(
            startDate: $this->getStartDate($request),
            billingPlan: $this->getBillingPlan($request),
            quantity: $this->getQuantity($request),
            currency: $request->getParsedBody()->amountCurrency ?? null,
        );

        $attributes = $this->prepareService->prepareAttributes($template, $data);

        return ResponseComposer::json($attributes);
    }

    /**
     * @throws BadRequest
     * @throws Forbidden
     * @throws NotFound
     */
    private function getTemplate(Request $request): SubscriptionTemplate
    {
        $id = $request->getRouteParam('id') ?? throw new BadRequest();

        return $this->entityProvider->getByClass(SubscriptionTemplate::class, $id);
    }

    /**
     * @throws BadRequest
     * @throws Forbidden
     * @throws NotFound
     */
    private function getBillingPlan(Request $request): SubscriptionBillingPlan
    {
        $id = $request->getParsedBody()->billingPlanId ?? null;

        if (!is_string($id)) {
            throw new BadRequest("No billingPlanId");
        }

        return $this->entityProvider->getByClass(SubscriptionBillingPlan::class, $id);
    }

    /**
     * @throws BadRequest
     */
    private function getStartDate(Request $request): Date
    {
        $raw = $request->getParsedBody()->startDate ?? null;

        if (!is_string($raw)) {
            throw new BadRequest("No billingPlanId");
        }

        try {
            return Date::fromString($raw);
        } catch (Exception $e) {
            throw new BadRequest("Bad date.", 400, $e);
        }
    }

    /**
     * @throws BadRequest
     */
    private function getQuantity(Request $request): float
    {
        $raw = $request->getParsedBody()->quantity ?? null;

        if ($raw === null) {
            return 1.0;
        }

        if (!is_numeric($raw)) {
            throw new BadRequest("No quantity");
        }

        return (float) $raw;
    }
}
