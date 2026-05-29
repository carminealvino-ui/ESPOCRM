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

use Espo\Modules\Sales\Entities\Subscription;
use Espo\ORM\EntityManager;

class UpdateBillingState
{
    public function __construct(
        private EntityManager $entityManager,
        private SubscriptionRepository $subscriptionRepository,
    ) {}

    public function update(Subscription $subscription): void
    {
        $this->entityManager
            ->getTransactionManager()
            ->run(fn () => $this->processInTransaction($subscription));
    }

    private function processInTransaction(Subscription $subscription): void
    {
        $this->subscriptionRepository->refreshAndLock($subscription);

        if ($this->toClear($subscription)) {
            $subscription->setBillingState(Subscription::BILLING_STATE_CLEAR);
        } else if ($this->toDue($subscription)) {
            $subscription->setBillingState(Subscription::BILLING_STATE_DUE);

            // @todo Past Due?
        }

        $this->entityManager->saveEntity($subscription);
    }

    private function toClear(Subscription $subscription): bool
    {
        return $this->subscriptionRepository->isSubscriptionClear($subscription->getId());
    }

    private function toDue(Subscription $subscription): bool
    {
        return $this->subscriptionRepository->isSubscriptionDue($subscription->getId());
    }
}
