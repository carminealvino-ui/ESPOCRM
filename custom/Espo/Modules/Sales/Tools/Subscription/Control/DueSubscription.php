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
use Espo\Modules\Sales\Tools\Subscription\SubscriptionRepository;
use Espo\ORM\EntityManager;

class DueSubscription
{
    public function __construct(
        private EntityManager $entityManager,
        private SubscriptionRepository $subscriptionRepository,
    ) {}

    public function process(Subscription $subscription): void
    {
        $this->subscriptionRepository->refreshAndLock($subscription);

        if ($subscription->getBillingState() !== Subscription::BILLING_STATE_CLEAR) {
            return;
        }

        if (!$this->subscriptionRepository->isSubscriptionDue($subscription->getId())) {
            return;
        }

        $subscription->setBillingState(Subscription::BILLING_STATE_DUE);

        $this->entityManager->saveEntity($subscription);
    }
}
