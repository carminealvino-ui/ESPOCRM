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

use Espo\Core\Field\Currency;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Entities\PaymentInstallment;
use Espo\ORM\EntityManager;
use Espo\ORM\Name\Attribute;

class PaymentInstallmentsStatusUpdater
{
    public function __construct(
        private EntityManager $entityManager,
    ) {}

    public function update(Invoice $invoice): void
    {
        $this->entityManager->getTransactionManager()->run(function () use ($invoice) {
            $invoice = $this->getInvoice($invoice->getId());

            if (!$invoice) {
                return;
            }

            $this->updateInternal($invoice);
        });
    }

    private function updateInternal(Invoice $invoice): void
    {
        $due = $invoice->getAmountDue();
        $total = $invoice->getGrandTotalAmount();

        if (!$due || !$total) {
            return;
        }

        $paid = $total->subtract($due);

        $terms = $invoice->getInstallmentCollection();
        $sum = Currency::create('0', $total->getCode());

        foreach ($terms as $term) {
            $amount = $term->getAmount();

            if ($sum->add($amount)->compare($paid) <= 0) {
                $status = PaymentInstallment::STATUS_SETTLED;
            } else if ($sum->compare($paid) < 0) {
                $status = PaymentInstallment::STATUS_PARTIALLY_SETTLED;
            } else {
                $status = PaymentInstallment::STATUS_UNSETTLED;
            }

            $sum = $sum->add($amount);

            if ($status === $term->getStatus()) {
                continue;
            }

            $term->setStatus($status);
            $this->entityManager->saveEntity($term);
        }
    }

    private function getInvoice(string $id): ?Invoice
    {
        $this->entityManager
            ->getRDBRepositoryByClass(Invoice::class)
            ->forUpdate()
            ->select(Attribute::ID)
            ->sth()
            ->where([Attribute::ID => $id])
            ->findOne();

        return $this->entityManager
            ->getRDBRepositoryByClass(Invoice::class)
            ->where([Attribute::ID => $id])
            ->findOne();
    }
}
