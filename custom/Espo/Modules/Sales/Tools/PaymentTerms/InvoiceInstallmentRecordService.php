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

namespace Espo\Modules\Sales\Tools\PaymentTerms;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Record\Collection;
use Espo\Core\Select\SearchParams;
use Espo\Core\Select\SelectBuilderFactory;
use Espo\Core\Field\Currency;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Entities\PaymentInstallment;
use Espo\Modules\Sales\Tools\Invoice\InvoiceStatusProvider;
use Espo\ORM\EntityManager;
use Espo\ORM\Query\Select;

class InvoiceInstallmentRecordService
{
    public function __construct(
        private EntityManager $entityManager,
        private SelectBuilderFactory $selectBuilderFactory,
        private InvoiceStatusProvider $invoiceStatusProvider,
    ) {}

    /**
     * @return Collection<PaymentInstallment>
     * @throws BadRequest
     * @throws Forbidden
     */
    public function getInstallments(Invoice $invoice, SearchParams $params): Collection
    {
        $query = $this->buildQuery($invoice, $params);

        $builder = $this->entityManager
            ->getRDBRepositoryByClass(PaymentInstallment::class)
            ->clone($query);

        $collection = $builder->find();

        $due = $invoice->getAmountDue();

        if (
            $due &&
            $invoice->isIssued() &&
            $invoice->getGrandTotalAmount()
        ) {
            $sum = Currency::create('0', $due->getCode());
            $paid = $invoice->getGrandTotalAmount()->subtract($due);

            $isCanceled = in_array($invoice->getStatus(), $this->invoiceStatusProvider->getCanceled());

            foreach ($collection as $term) {
                $this->prepareItem($term, $paid, $sum, $isCanceled);
            }
        }

        /** @var Collection<PaymentInstallment> */
        return Collection::create($collection, $builder->count());
    }

    /**
     * @throws BadRequest
     * @throws Forbidden
     */
    private function buildQuery(
        Invoice $invoice,
        SearchParams $params,
    ): Select {

        return $this->selectBuilderFactory
            ->create()
            ->from(PaymentInstallment::ENTITY_TYPE)
            ->withSearchParams($params)
            ->withComplexExpressionsForbidden()
            ->buildQueryBuilder()
            ->where([
                PaymentInstallment::FIELD_SOURCE . 'Id' => $invoice->getId(),
                PaymentInstallment::FIELD_SOURCE . 'Type' => $invoice->getEntityType(),
            ])
            ->order(PaymentInstallment::FIELD_ORDER)
            ->build();
    }

    private function prepareItem(PaymentInstallment $item, Currency $paid, Currency &$sum, bool $isCanceled): void
    {
        $amount = $item->getAmount();

        $item->setState(PaymentInstallment::STATE_DUE);

        if ($isCanceled) {
            $item->setState(PaymentInstallment::STATE_CANCELED);
        }

        $due = $amount;

        if ($sum->add($amount)->compare($paid) <= 0) {
            $item->setState(PaymentInstallment::STATE_SETTLED);

            $due = Currency::create('0.000', $amount->getCode());
        } else if ($sum->compare($paid) < 0) {
            $item->setState(PaymentInstallment::STATE_PARTIALLY_SETTLED);

            $due = $sum->add($amount)->subtract($paid);
        }

        if (!$isCanceled) {
            $item->setAmountDue($due);
        }

        $sum = $sum->add($amount);
    }
}
