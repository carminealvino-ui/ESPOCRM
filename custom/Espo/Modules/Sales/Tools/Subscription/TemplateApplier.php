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

use Espo\Core\Field\Date;
use Espo\Modules\Sales\Entities\Subscription;
use Espo\Modules\Sales\Entities\SubscriptionPeriod as Period;
use Espo\Modules\Sales\Entities\SubscriptionTemplate;
use Espo\ORM\EntityManager;
use RuntimeException;

class TemplateApplier
{
    public function __construct(
        private EntityManager $entityManager,
        private Util $util,
        private CreateUpcomingPeriod $createUpcomingPeriod,
    ) {}

    public function apply(Subscription $subscription, SubscriptionTemplate $template): void
    {
        $trialPeriod = $this->prepareTrial($template, $subscription);

        $copy = $this->getCopy($subscription);

        $this->controlStatusTrial($trialPeriod, $copy);
        $this->createPeriod($copy, $trialPeriod);

        $this->syncEntities($subscription, $copy);
    }

    private function prepareTrial(SubscriptionTemplate $template, Subscription $subscription): ?Period
    {
        $hasTrial = $subscription->has(Subscription::FIELD_HAS_TRIAL) ?
            $subscription->get(Subscription::FIELD_HAS_TRIAL) :
            $template->hasTrial();

        if (
            !$hasTrial ||
            !$template->getTrialPeriodDays()
        ) {
            return null;
        }

        $trialPeriod = $this->entityManager->getRDBRepositoryByClass(Period::class)->getNew();

        $startDate = $subscription->getStartDate() ?? throw new RuntimeException("No start date.");

        $trialPeriod
            ->setSubscription($subscription)
            ->setStartDate($startDate)
            ->setEndDate($startDate->addDays($template->getTrialPeriodDays()))
            ->setStatus(
                $this->isTodayOrBefore($startDate) ?
                    Period::STATUS_ACTIVE :
                    Period::STATUS_SCHEDULED
            )
            ->setBillingStatus(Period::BILLING_STATUS_SETTLED)
            ->setType(Period::TYPE_TRIAL);

        $this->entityManager->saveEntity($trialPeriod);

        return $trialPeriod;
    }

    /**
     * Prevents loop.
     */
    private function getCopy(Subscription $subscription): Subscription
    {
        $id = $subscription->getId();

        $copy = $this->entityManager->getRDBRepositoryByClass(Subscription::class)->getById($id) ??
            throw new RuntimeException();

        $copy->set(Subscription::FIELD_START_DATE, $subscription->getStartDate()?->toString());

        return $copy;
    }

    private function createPeriod(Subscription $subscription, ?Period $trialPeriod): void
    {
        $startDate = $this->getStartDate($trialPeriod, $subscription);

        $pointerData = new PointerData(
            subscription: $subscription,
            date: $startDate,
            holdUntilBillingComplete: true,
        );

        $this->entityManager
            ->getTransactionManager()
            ->run(fn () => $this->createUpcomingPeriod->process($pointerData));
    }

    private function isTodayOrBefore(Date $startDate): bool
    {
        return $startDate->isLessThanOrEqualTo($this->util->getToday());
    }

    private function controlStatusTrial(?Period $trialPeriod, Subscription $copy): void
    {
        if (
            !$trialPeriod ||
            !$this->isTodayOrBefore($trialPeriod->getStartDate())
        ) {
            return;
        }

        $copy->setStatus(Subscription::STATUS_TRIAL);

        $this->entityManager->saveEntity($copy);
    }

    private function getStartDate(?Period $trialPeriod, Subscription $subscription): Date
    {
        return $trialPeriod?->getEndDate() ??
            $subscription->getStartDate() ??
            throw new RuntimeException("No start date.");
    }

    private function syncEntities(Subscription $subscription, Subscription $copy): void
    {
        $subscription
            ->setAnchorDay($copy->getAnchorDay())
            ->setStatus($copy->getStatus());
    }
}
