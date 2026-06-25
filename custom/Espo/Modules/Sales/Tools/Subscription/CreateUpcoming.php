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

use Error;
use Espo\Core\Utils\Log;
use Espo\Modules\Sales\Entities\SubscriptionBillingPlan as BillingPlan;
use Espo\Modules\Sales\Entities\SubscriptionPeriod as Period;
use Espo\Modules\Sales\Tools\Subscription\Exceptions\NotProperStatus;
use Espo\ORM\EntityManager;
use Exception;
use Monolog\Level;

class CreateUpcoming
{
    private const INVOICE_IGNORE_PERIOD = '1 year';

    public function __construct(
        private EntityManager $entityManager,
        private Util $util,
        private Log $log,
        private PeriodRepository $periodRepository,
        private BillingPlanRepository $billingPlanRepository,
        private CreateUpcomingPeriod $createUpcomingPeriod,
        private CreateInvoiceForPeriod $createInvoiceForPeriod,
    ) {}

    public function run(): void
    {
        $billingPlans = $this->billingPlanRepository->findActive();

        foreach ($billingPlans as $billingPlan) {
            $this->createUpcomingForBillingPlan($billingPlan);
        }

        $this->createInvoices();
    }

    private function createUpcomingForBillingPlan(BillingPlan $billingPlan): void
    {
        $day = $this->util->getToday()
            ->addDays($billingPlan->getInvoiceLeadTimeDays());

        $periods = $this->periodRepository->findEnding($billingPlan, $day);

        foreach ($periods as $period) {
            try {
                $this->createUpcomingForPeriodInTransaction($period);
            } catch (Exception|Error $e) {
                $this->log->critical("Error while creating next period after SubscriptionPeriod {id}.", [
                    'id' => $period->getId(),
                    'exception' => $e,
                ]);
            }
        }
    }

    private function createUpcomingForPeriodInTransaction(Period $period): void
    {
        if (
            $period->holdUntilBillingComplete() &&
            $period->getBillingStatus() !== Period::BILLING_STATUS_SETTLED
        ) {
            // @todo Log info.
            return;
        }

        $pointerData = new PointerData(
            subscription: $period->getSubscription(),
            date: $period->getEndDate(),
        );

        $this->entityManager
            ->getTransactionManager()
            ->run(fn () => $this->createUpcomingPeriod->process($pointerData));
    }

    private function createInvoices(): void
    {
        $date = $this->util->getToday()->modify('-' . self::INVOICE_IGNORE_PERIOD);

        $periods = $this->periodRepository->findAutomaticInvoicePending($date);

        foreach ($periods as $period) {
            try {
                $this->createInvoiceForPeriodInTransaction($period);
            } catch (Exception|Error $e) {
                $level = $e instanceof NotProperStatus ?
                    Level::Warning : Level::Critical;

                $this->log->log($level, "Error while creating invoice for SubscriptionPeriod {id}.", [
                    'id' => $period->getId(),
                    'exception' => $e,
                ]);
            }
        }
    }

    private function createInvoiceForPeriodInTransaction(Period $period): void
    {
        $this->entityManager
            ->getTransactionManager()
            ->run(fn () => $this->createInvoiceForPeriod->process($period));
    }
}
