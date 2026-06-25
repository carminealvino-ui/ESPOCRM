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

namespace Espo\Modules\Sales\Tools\ReceiptOrder\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Modules\Sales\Entities\ReceiptOrder;
use Espo\Modules\Sales\Tools\ReceiptOrder\SerialNumberService;

/**
 * @noinspection PhpUnused
 */
class PostImportSerialNumbers implements Action
{
    public function __construct(
        private Acl $acl,
        private SerialNumberService $serialNumberService
    ) {}

    /**
     * @return Response
     * @throws BadRequest
     * @throws Conflict
     * @throws Forbidden
     * @throws NotFound
     */
    public function process(Request $request): Response
    {
        $id = $request->getRouteParam('id');
        $productId = $request->getParsedBody()->productId ?? null;
        $items = $request->getParsedBody()->items ?? null;

        if (!$this->acl->checkScope(ReceiptOrder::ENTITY_TYPE, Acl\Table::ACTION_EDIT)) {
            throw new Forbidden();
        }

        if (!$id) {
            throw new BadRequest();
        }

        if (!$productId || !is_string($productId)) {
            throw new BadRequest("No product ID.");
        }

        if (!is_array($items)) {
            throw new BadRequest("No items.");
        }

        if ($items[count($items) - 1] === null) {
            array_pop($items);
        }

        // @todo Trim all items.

        foreach ($items as $item) {
            if (!is_string($item)) {
                throw new BadRequest("Non-string serial number.");
            }

            if ($item === '') {
                throw new BadRequest("Empty string serial number.");
            }
        }

        $this->serialNumberService->receiveSerialNumbers($id, $productId, $items);

        return ResponseComposer::json(true);
    }
}
