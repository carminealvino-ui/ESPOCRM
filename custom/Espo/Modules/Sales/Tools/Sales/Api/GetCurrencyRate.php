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

namespace Espo\Modules\Sales\Tools\Sales\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Currency\ConfigDataProvider;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Field\Date;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Entities\PaymentEntry;
use Espo\Modules\Sales\Entities\SupplierBill;
use Espo\Tools\Currency\Exceptions\NotEnabled;
use Espo\Tools\Currency\RateEntryProvider;
use Exception;

/**
 * Can be used only as of v9.3.0.
 *
 * @noinspection PhpUnused
 */
class GetCurrencyRate implements Action
{
    public function __construct(
        private Acl $acl,
        private RateEntryProvider $rateEntryProvider,
        private ConfigDataProvider $configDataProvider,
    ) {}

    public function process(Request $request): Response
    {
        $this->checkAccess();

        $date = $this->fetchDate($request);

        $baseCode = $request->getQueryParam('baseCode');

        if (!is_string($baseCode)) {
            throw new BadRequest("No 'baseCode'.");
        }

        $targetCode = $request->getQueryParam('targetCode');

        if (!is_string($targetCode)) {
            throw new BadRequest("No 'targetCode'.");
        }

        $rate = $this->getRate($baseCode, $targetCode, $date);

        return ResponseComposer::json(['rate' => $rate]);
    }

    /**
     * @return ?numeric-string
     * @throws Conflict
     */
    private function getRate(string $baseCode, string $targetCode, Date $date): ?string
    {
        if ($baseCode !== $this->configDataProvider->getBaseCurrency()) {
            return null;
        }

        try {
            $rateEntry = $this->rateEntryProvider->getRateEntryOnAsOfDate($targetCode, $date);
        } catch (NotEnabled) {
            throw new Conflict("Currency '$targetCode' not enabled.");
        }

        return $rateEntry?->getRate();
    }

    /**
     * @throws Forbidden
     */
    private function checkAccess(): void
    {
        if (
            !$this->acl->checkScope(Invoice::ENTITY_TYPE, Acl\Table::ACTION_EDIT) &&
            !$this->acl->checkScope(Invoice::ENTITY_TYPE, Acl\Table::ACTION_CREATE) &&
            !$this->acl->checkScope(PaymentEntry::ENTITY_TYPE, Acl\Table::ACTION_EDIT) &&
            !$this->acl->checkScope(PaymentEntry::ENTITY_TYPE, Acl\Table::ACTION_CREATE) &&
            !$this->acl->checkScope(SupplierBill::ENTITY_TYPE, Acl\Table::ACTION_EDIT) &&
            !$this->acl->checkScope(SupplierBill::ENTITY_TYPE, Acl\Table::ACTION_CREATE)
        ) {
            throw new Forbidden("No edit access to Invoice, SupplierBill or PaymentEntry scopes.");
        }
    }

    /**
     * @throws BadRequest
     */
    private function fetchDate(Request $request): Date
    {
        $dateString = $request->getQueryParam('date');

        if (!is_string($dateString)) {
            throw new BadRequest("No 'date'.");
        }

        try {
            $date = Date::fromString($dateString);
        } catch (Exception) {
            throw new BadRequest("Bad 'date'.");
        }

        return $date;
    }
}
