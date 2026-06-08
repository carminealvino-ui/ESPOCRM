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

namespace Espo\Modules\Sales\Tools\TaxPurchaseRule\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Record\EntityProvider;
use Espo\Modules\Crm\Entities\Account;
use Espo\Modules\Sales\Entities\PurchaseOrder;
use Espo\Modules\Sales\Entities\Supplier;
use Espo\Modules\Sales\Entities\SupplierBill;
use Espo\Modules\Sales\Entities\SupplierCredit;
use Espo\Modules\Sales\Tools\TaxPurchaseRule\RuleService;

/**
 * @noinspection PhpUnused
 */
class GetTaxRuleSupplier implements Action
{
    public function __construct(
        private EntityProvider $entityProvider,
        private Acl $acl,
        private RuleService $service,
    ) {}

    public function process(Request $request): Response
    {
        $this->checkAccess();

        $account = $this->getAccount($request);

        if (!$account) {
            return ResponseComposer::json(null);
        }

        $tax = $this->service->get($account);

        if (!$tax) {
            return ResponseComposer::json(null);
        }

        return ResponseComposer::json([
            'id' => $tax->getId(),
            'name' => $tax->getName(),
            'rate' => $tax->getRate(),
            'taxCodeId' => $tax->getTaxCode()?->getId(),
            'taxCodeName' => $tax->getTaxCode()?->getName(),
            'shippingMode' => $tax->getShippingMode(),
        ]);
    }

    /**
     * @throws Forbidden
     */
    private function checkAccess(): void
    {
        if (
            !$this->acl->checkScope(PurchaseOrder::ENTITY_TYPE) &&
            !$this->acl->checkScope(SupplierBill::ENTITY_TYPE) &&
            !$this->acl->checkScope(SupplierCredit::ENTITY_TYPE)
        ) {
            throw new Forbidden("No access to purchase order scope.");
        }
    }

    /**
     * @throws BadRequest
     * @throws Forbidden
     * @throws NotFound
     */
    private function getAccount(Request $request): ?Account
    {
        $id = $request->getRouteParam('id') ?? throw new BadRequest();

        $supplier = $this->entityProvider->getByClass(Supplier::class, $id);

        return $supplier->getAccount();
    }
}
