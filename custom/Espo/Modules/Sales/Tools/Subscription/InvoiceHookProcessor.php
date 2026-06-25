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

namespace Espo\Modules\Sales\Tools\Subscription;

use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Entities\SubscriptionPeriod as Period;
use Espo\Modules\Sales\Entities\SubscriptionUpdate as Update;
use Espo\Modules\Sales\Tools\Invoice\InvoiceStatusProvider;
use Espo\ORM\EntityManager;
use Traversable;

class InvoiceHookProcessor
{
    public function __construct(
        private EntityManager $entityManager,
        private InvoiceStatusProvider $statusProvider,
    ) {}

    public function processDone(Invoice $invoice): void
    {
        if (!$this->isDone($invoice)) {
            return;
        }

        foreach ($this->getPeriods($invoice) as $period) {
            $this->processDonePeriod($invoice, $period);
        }

        foreach ($this->getUpdates($invoice) as $update) {
            $this->processDoneUpdate($invoice, $update);
        }
    }

    private function processDonePeriod(Invoice $invoice, Period $period): void
    {
        if ($this->periodHasAnotherOpenInvoice($period, $invoice)) {
            return;
        }

        $period->setBillingStatus(Period::BILLING_STATUS_SETTLED);

        $this->entityManager->saveEntity($period);
    }

    private function processDoneUpdate(Invoice $invoice, Update $update): void
    {
        if ($this->updateHasAnotherOpenInvoice($update, $invoice)) {
            return;
        }

        $update->setBillingStatus(Update::BILLING_STATUS_SETTLED);

        $this->entityManager->saveEntity($update);
    }

    /**
     * @return Traversable<int, Period>
     */
    private function getPeriods(Invoice $entity): Traversable
    {
        /** @var Traversable<int, Period> */
        return $this->entityManager
            ->getRelation($entity, Invoice::LINK_SUBSCRIPTION_PERIODS)
            ->find();
    }

    /**
     * @return Traversable<int, Update>
     */
    private function getUpdates(Invoice $entity): Traversable
    {
        /** @var Traversable<int, Update> */
        return $this->entityManager
            ->getRelation($entity, Invoice::LINK_SUBSCRIPTION_UPDATES)
            ->find();
    }

    private function isOpen(Invoice $invoice): bool
    {
        return !in_array($invoice->getStatus(), $this->statusProvider->getNotOpen());
    }

    private function isDone(Invoice $invoice): bool
    {
        return in_array($invoice->getStatus(), $this->statusProvider->getDone());
    }

    private function periodHasAnotherOpenInvoice(Period $period, Invoice $invoice): bool
    {
        foreach ($period->getInvoices() as $itInvoice) {
            if ($itInvoice->getId() === $invoice->getId()) {
                continue;
            }

            if ($this->isOpen($itInvoice)) {
                return true;
            }
        }

        return false;
    }

    private function updateHasAnotherOpenInvoice(Update $update, Invoice $invoice): bool
    {
        foreach ($update->getInvoices() as $itInvoice) {
            if ($itInvoice->getId() === $invoice->getId()) {
                continue;
            }

            if ($this->isOpen($itInvoice)) {
                return true;
            }
        }

        return false;
    }
}
