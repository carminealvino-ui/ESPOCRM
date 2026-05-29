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

namespace Espo\Modules\Sales\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Controllers\Record;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Modules\Sales\Entities\PurchaseOrder as PurchaseOrderEntity;
use Espo\Modules\Sales\Entities\ReceiptOrder as ReceiptOrderEntity;
use Espo\Modules\Sales\Entities\ReturnOrder as ReturnOrderEntity;
use Espo\Modules\Sales\Tools\ReceiptOrder\ConvertService;
use Espo\Modules\Sales\Tools\Quote\EmailService;
use Espo\Modules\Sales\Tools\ReceiptOrder\Service;
use stdClass;

/** @noinspection PhpUnused */
class ReceiptOrder extends Record
{
    /**
     * @return array<string, mixed>
     * @throws BadRequest
     * @throws Forbidden
     * @throws NotFound
     */
    public function actionGetAttributesFromPurchaseOrder(Request $request): array
    {
        $purchaseOrderId = $request->getQueryParam('purchaseOrderId');

        if (!$purchaseOrderId) {
            throw new BadRequest();
        }

        return $this->injectableFactory
            ->create(ConvertService::class)
            ->getAttributes(PurchaseOrderEntity::ENTITY_TYPE, $purchaseOrderId);
    }

    /**
     * @return array<string, mixed>
     * @throws BadRequest
     * @throws Forbidden
     * @throws NotFound
     */
    public function actionGetAttributesFromReturnOrder(Request $request): array
    {
        $returnOrderId = $request->getQueryParam('returnOrderId');

        if (!$returnOrderId) {
            throw new BadRequest();
        }

        return $this->injectableFactory
            ->create(ConvertService::class)
            ->getAttributes(ReturnOrderEntity::ENTITY_TYPE, $returnOrderId);
    }

    /**
     * @return array<string, mixed>
     * @throws BadRequest
     * @throws Forbidden
     * @throws Error
     * @throws NotFound
     */
    public function postActionGetAttributesForEmail(Request $request): array
    {
        $data = $request->getParsedBody();

        if (empty($data->id) || empty($data->templateId)) {
            throw new BadRequest();
        }

        return $this->injectableFactory
            ->create(EmailService::class)
            ->getAttributes(ReceiptOrderEntity::ENTITY_TYPE, $data->id, $data->templateId);
    }

    /**
     * @throws BadRequest
     * @throws NotFound
     * @throws Forbidden
     */
    public function postActionCreateForPurchaseOrder(Request $request): stdClass
    {
        $purchaseOrderId = $request->getParsedBody()->purchaseOrderId ?? null;
        $dataList = $request->getParsedBody()->dataList ?? null;

        if (!is_string($purchaseOrderId) || !is_array($dataList)) {
            throw new BadRequest();
        }

        foreach ($dataList as $item) {
            if (!is_object($item)) {
                throw new BadRequest();
            }
        }

        $collection = $this->injectableFactory
            ->create(Service::class)
            ->createFromPurchaseOrder($purchaseOrderId, $dataList);

        return (object) [
            'list' => $collection->getValueMapList(),
        ];
    }
}
