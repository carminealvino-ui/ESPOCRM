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

use Error;
use Espo\Core\Utils\Log;
use Espo\Modules\Sales\Tools\Subscription\PeriodRepository;
use Espo\Modules\Sales\Tools\Subscription\SubscriptionRepository;
use Espo\Modules\Sales\Tools\Subscription\Util;
use Espo\ORM\EntityManager;
use Exception;

class ControlStatus
{
    public function __construct(
        private PeriodRepository $periodRepository,
        private Util $util,
        private EntityManager $entityManager,
        private Log $log,
        private DueSubscription $dueSubscription,
        private InvoicePastDueForPeriod $invoicePastDueForPeriod,
        private PauseForPeriod $pauseForPeriod,
        private UnpauseForPeriod $unpauseForPeriod,
        private SubscriptionRepository $subscriptionRepository,
        private StopSubscription $stopSubscription,
        private ClearSubscription $clearForPeriod,
    ) {}

    public function run(): void
    {
        $this->processUnpause();
        $this->processClear();
        $this->processInvoiceDue();
        $this->processInvoicePastDue();
        $this->processPause();
        $this->processEnd();
    }

    private function processClear(): void
    {
        $subscriptions = $this->subscriptionRepository->findNonClearWithNoInvoicedPeriods();

        foreach ($subscriptions as $subscription) {
            try {
                $this->entityManager
                    ->getTransactionManager()
                    ->run(fn () => $this->clearForPeriod->process($subscription));
            } catch (Exception|Error $e) {
                $this->log->critical("Error while handling clear billing state for Subscription {id}.", [
                    'id' => $subscription->getId(),
                    'exception' => $e,
                ]);
            }
        }
    }

    private function processInvoiceDue(): void
    {
        $subscriptions = $this->subscriptionRepository->findClearWithInvoicedPeriods();

        foreach ($subscriptions as $subscription) {
            try {
                $this->entityManager
                    ->getTransactionManager()
                    ->run(fn () => $this->dueSubscription->process($subscription));
            } catch (Exception|Error $e) {
                $this->log->critical("Error while handling invoice due for Subscription {id}.", [
                    'id' => $subscription->getId(),
                    'exception' => $e,
                ]);
            }
        }
    }

    private function processInvoicePastDue(): void
    {
        $periods = $this->periodRepository->findWithPastDueInvoices($this->util->getToday());

        foreach ($periods as $period) {
            try {
                $this->entityManager
                    ->getTransactionManager()
                    ->run(fn () => $this->invoicePastDueForPeriod->process($period));
            } catch (Exception|Error $e) {
                $this->log->critical("Error while handling invoice past due for SubscriptionPeriod {id}.", [
                    'id' => $period->getId(),
                    'exception' => $e,
                ]);
            }
        }
    }

    private function processPause(): void
    {
        $periods = $this->periodRepository->findTodaysToPause($this->util->getToday());

        foreach ($periods as $period) {
            try {
                $this->entityManager
                    ->getTransactionManager()
                    ->run(fn () => $this->pauseForPeriod->process($period));
            } catch (Exception|Error $e) {
                $this->log->critical("Error while handling pause SubscriptionPeriod {id}.", [
                    'id' => $period->getId(),
                    'exception' => $e,
                ]);
            }
        }
    }

    private function processUnpause(): void
    {
        $periods = $this->periodRepository->findTodaysToUnpause($this->util->getToday());

        foreach ($periods as $period) {
            try {
                $this->entityManager
                    ->getTransactionManager()
                    ->run(fn () => $this->unpauseForPeriod->process($period));
            } catch (Exception|Error $e) {
                $this->log->critical("Error while handling un-pause SubscriptionPeriod {id}.", [
                    'id' => $period->getId(),
                    'exception' => $e,
                ]);
            }
        }
    }

    private function processEnd(): void
    {
        $subscriptions = $this->subscriptionRepository->findActualToEnd($this->util->getToday());

        foreach ($subscriptions as $subscription) {
            try {
                $this->entityManager
                    ->getTransactionManager()
                    ->run(fn () => $this->stopSubscription->process($subscription));
            } catch (Exception|Error $e) {
                $this->log->critical("Error while stopping ended Subscription {id}.", [
                    'id' => $subscription->getId(),
                    'exception' => $e,
                ]);
            }
        }
    }
}
