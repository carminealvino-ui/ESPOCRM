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

namespace Espo\Modules\Sales\Tools\Payment;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Record\Collection;
use Espo\Core\Select\SearchParams;
use Espo\Core\Select\SelectBuilderFactory;
use Espo\Modules\Sales\Entities\CreditNote;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Entities\PaymentAllocation;
use Espo\Modules\Sales\Entities\SupplierBill;
use Espo\Modules\Sales\Entities\SupplierCredit;
use Espo\ORM\EntityManager;
use Espo\ORM\Query\Select;

class InvoicePaymentRecordService
{
    public function __construct(
        private EntityManager $entityManager,
        private SelectBuilderFactory $selectBuilderFactory,
    ) {}

    /**
     * @return Collection<PaymentAllocation>
     * @throws BadRequest
     * @throws Forbidden
     */
    public function getAllocations(
        Invoice|CreditNote|SupplierBill|SupplierCredit $invoice,
        SearchParams $params,
    ): Collection {

        $query = $this->buildQuery($invoice, $params);

        $builder = $this->entityManager
            ->getRDBRepositoryByClass(PaymentAllocation::class)
            ->clone($query);

        /** @var Collection<PaymentAllocation> */
        return Collection::create($builder->find(), $builder->count());
    }

    /**
     * @throws BadRequest
     * @throws Forbidden
     */
    private function buildQuery(
        Invoice|CreditNote|SupplierBill|SupplierCredit $invoice,
        SearchParams $params,
    ): Select {

        return $this->selectBuilderFactory
            ->create()
            ->from(PaymentAllocation::ENTITY_TYPE)
            ->withSearchParams($params)
            ->withComplexExpressionsForbidden()
            ->buildQueryBuilder()
            ->where([
                'targetId' => $invoice->getId(),
                'targetType' => $invoice->getEntityType(),
            ])
            ->build();
    }
}
