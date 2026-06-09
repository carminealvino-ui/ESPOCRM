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
use Espo\ORM\EntityManager;

class CreateMissingForSubscription
{
    public function __construct(
        private EntityManager $entityManager,
        private SubscriptionRepository $subscriptionRepository,
        private PeriodRepository $periodRepository,
    ) {}

    public function process(Subscription $subscription, Date $today): void
    {
        $this->subscriptionRepository->refreshAndLock($subscription);

        if (!$this->subscriptionRepository->hasNoPeriodOnDate($subscription, $today)) {
            return;
        }

        $nextPeriod = $this->periodRepository->findNextPeriod($subscription, $today);

        if (!$nextPeriod) {
            return;
        }

        $period = $this->periodRepository->getNew();

        $period
            ->setSubscription($subscription)
            ->setType(Period::TYPE_PAUSE)
            ->setBillingStatus(Period::BILLING_STATUS_SETTLED)
            ->setStartDate($today)
            ->setEndDate($nextPeriod->getStartDate());

        $this->entityManager->saveEntity($period);
    }
}
