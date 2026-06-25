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
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Field\Date;
use Espo\Core\Record\EntityProvider;
use Espo\Modules\Sales\Entities\Subscription;
use Espo\Modules\Sales\Tools\Subscription\SubscriptionOrderItem;
use Espo\Modules\Sales\Tools\Subscription\UpdateAmounts\Data;
use Espo\Modules\Sales\Tools\Subscription\UpdateService;
use stdClass;

/**
 * @noinspection PhpUnused
 */
class PostCalculateUpdateAmounts implements Action
{
    public function __construct(
        private EntityProvider $entityProvider,
        private Acl $acl,
        private UpdateService $updateService,
    ) {}

    public function process(Request $request): Response
    {
        $subscription = $this->getSubscription($request);

        $data = new Data(
            date: $this->getDate($request),
            items: $this->getItems($request),
            currency: $this->getCurrency($request),
        );

        $attributes = $this->updateService->calculateAttributes($subscription, $data);

        return ResponseComposer::json($attributes);
    }

    /**
     * @throws BadRequest
     * @throws Forbidden
     * @throws NotFound
     */
    private function getSubscription(Request $request): Subscription
    {
        $id = $request->getRouteParam('id') ?? throw new BadRequest();

        $subscription = $this->entityProvider->getByClass(Subscription::class, $id);

        if (!$this->acl->checkEntityEdit($subscription)) {
            throw new Forbidden("No 'edit' access.");
        }

        return $subscription;
    }

    /**
     * @return SubscriptionOrderItem[]
     * @throws BadRequest
     */
    private function getItems(Request $request): array
    {
        $rawItems = $request->getParsedBody()->itemList ?? null;

        if (!is_array($rawItems)) {
            throw new BadRequest("No itemList.");
        }

        $items = [];

        foreach ($rawItems as $rawItem) {
            if (!$rawItem instanceof stdClass) {
                throw new BadRequest("Bad item.");
            }

            $items[] = SubscriptionOrderItem::fromRaw($rawItem);
        }

        return $items;
    }

    /**
     * @throws BadRequest
     */
    private function getDate(Request $request): Date
    {
        $dateRaw = $request->getParsedBody()->date ?? null;

        if (!is_string($dateRaw)) {
            throw new BadRequest("No date.");
        }

        return Date::fromString($dateRaw);
    }

    /**
     * @throws BadRequest
     */
    private function getCurrency(Request $request): string
    {
        $amountCurrency = $request->getParsedBody()->currency ?? null;

        if (!is_string($amountCurrency)) {
            throw new BadRequest("No currency.");
        }

        return $amountCurrency;
    }
}
