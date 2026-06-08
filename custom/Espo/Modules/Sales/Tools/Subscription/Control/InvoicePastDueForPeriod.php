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

namespace Espo\Modules\Sales\Tools\Subscription\Control;

use Espo\Modules\Sales\Entities\Subscription;
use Espo\Modules\Sales\Entities\SubscriptionPeriod as Period;
use Espo\Modules\Sales\Tools\Invoice\InvoiceStatusProvider;
use Espo\Modules\Sales\Tools\Subscription\SubscriptionRepository;
use Espo\Modules\Sales\Tools\Subscription\Util;
use Espo\ORM\EntityManager;

class InvoicePastDueForPeriod
{
    public function __construct(
        private EntityManager $entityManager,
        private SubscriptionRepository $subscriptionRepository,
        private Util $util,
        private InvoiceStatusProvider $invoiceStatusProvider,
    ) {}

    public function process(Period $period): void
    {
        $subscription = $this->getSubscription($period);

        $subscription->setBillingState(Subscription::BILLING_STATE_PAST_DUE);

        if ($this->hasPostGraceInvoiceAndNotFuture($period)) {
            $subscription->setStatus(Subscription::STATUS_STOPPED);
        }

        $this->entityManager->saveEntity($subscription);
    }

    private function getSubscription(Period $period): Subscription
    {
        $this->subscriptionRepository->refreshAndLock($period->getSubscription());

        return $period->getSubscription();
    }

    private function hasPostGraceInvoiceAndNotFuture(Period $period): bool
    {
        $subscription = $period->getSubscription();
        $today = $this->util->getToday();

        if ($period->getStartDate()->isGreaterThan($today)) {
            return false;
        }

        $gracePeriod = $subscription->getGracePeriodDays() ?? $subscription->getBillingPlan()->getGracePeriodDays();

        foreach ($period->getInvoices() as $invoice) {
            if (
                $invoice->getDateDue() &&
                $invoice->getDateDue()->addDays($gracePeriod)->isLessThan($today) &&
                !in_array($invoice->getStatus(), $this->invoiceStatusProvider->getNotOpen())
            ) {
                return true;
            }
        }

        return false;
    }
}
