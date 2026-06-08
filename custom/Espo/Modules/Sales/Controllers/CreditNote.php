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
use Espo\Modules\Sales\Entities\CreditNote as CreditNoteEntity;
use Espo\Modules\Sales\Entities\Invoice as InvoiceEntity;
use Espo\Modules\Sales\Tools\Quote\Convert\Params;
use Espo\Modules\Sales\Tools\Quote\ConvertService;
use Espo\Modules\Sales\Tools\Invoice\EmailService;

/**
 * @noinspection PhpUnused
 */
class CreditNote extends Record
{
    /**
     * @return array<string, mixed>
     * @throws BadRequest
     * @throws Forbidden
     * @throws NotFound
     */
    public function actionGetAttributesFromInvoice(Request $request): array
    {
        $invoiceId = $request->getQueryParam('invoiceId');
        $issue = $request->getQueryParam('issue') === 'true';

        if (!$invoiceId) {
            throw new BadRequest();
        }

        $params = new Params(
            issue: $issue,
        );

        return $this->injectableFactory
            ->create(ConvertService::class)
            ->getAttributes(CreditNoteEntity::ENTITY_TYPE, InvoiceEntity::ENTITY_TYPE, $invoiceId, $params);
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

        $format = $data->format ?? null;

        if ($format !== null && !is_string($format)) {
            throw new BadRequest("Bad format.");
        }

        return $this->injectableFactory
            ->create(EmailService::class)
            ->getAttributes(
                sourceType: CreditNoteEntity::ENTITY_TYPE,
                sourceId: $data->id,
                templateId: $data->templateId,
                format: $format,
            );
    }
}
