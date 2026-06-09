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

namespace Espo\Modules\Sales\Tools\TaxItemRule\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Record\EntityProvider;
use Espo\Core\Select\SearchParams;
use Espo\Core\Utils\Json;
use Espo\Modules\Sales\Entities\TaxItemRule;
use Espo\Modules\Sales\Tools\TaxItemRule\MoveService;

/**
 * @noinspection PhpUnused
 */
class PostMove implements Action
{
    public function __construct(
        private EntityProvider $entityProvider,
        private Acl $acl,
        private MoveService $service,
    ) {}

    public function process(Request $request): Response
    {
        $taxRate = $this->getTaxRule($request);
        $type = $this->fetchType($request);
        $searchParams = $this->fetchSearchParams($request);

        $this->service->move($taxRate, $type, $searchParams);

        return ResponseComposer::json(true);
    }

    /**
     * @throws BadRequest
     * @throws Forbidden
     * @throws NotFound
     */
    private function getTaxRule(Request $request): TaxItemRule
    {
        $id = $request->getRouteParam('id') ?? throw new BadRequest();

        $rule = $this->entityProvider->getByClass(TaxItemRule::class, $id);

        if (!$this->acl->checkEntityEdit($rule)) {
            throw new Forbidden("No edit access.");
        }

        return $rule;
    }

    /**
     * @return MoveService::TYPE_TOP|MoveService::TYPE_UP|MoveService::TYPE_DOWN|MoveService::TYPE_BOTTOM
     * @throws BadRequest
     */
    private function fetchType(Request $request): string
    {
        $type = $request->getRouteParam('type') ?? throw new BadRequest();

        if (
            !in_array($type, [
                MoveService::TYPE_TOP,
                MoveService::TYPE_UP,
                MoveService::TYPE_DOWN,
                MoveService::TYPE_BOTTOM,
            ])
        ) {
            throw new BadRequest("Bad type.");
        }

        return $type;
    }

    private function fetchSearchParams(Request $request): SearchParams
    {
        $body = $request->getParsedBody();

        $searchParams = SearchParams::create();

        if ($body->whereGroup ?? null) {
            $rawWhere = Json::decode(Json::encode($body->whereGroup), true);

            $searchParams = SearchParams::fromRaw(['where' => $rawWhere]);
        }

        return $searchParams;
    }
}
