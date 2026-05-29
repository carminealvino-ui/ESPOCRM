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

namespace Espo\Modules\Sales\Tools\Subscription\Jobs;

use Espo\Core\Job\Job;
use Espo\Core\Job\Job\Data;
use Espo\Modules\Sales\Entities\Subscription;
use Espo\Modules\Sales\Tools\Subscription\UpdateBillingState;
use Espo\ORM\EntityManager;
use RuntimeException;

class UpdateSubscriptionBillingStatus implements Job
{
    public function __construct(
        private EntityManager $entityManager,
        private UpdateBillingState $updateBillingStatus,
    ) {}

    public function run(Data $data): void
    {
        $subscription = $this->getSubscription($data);

        $this->updateBillingStatus->update($subscription);
    }

    private function getSubscription(Data $data): Subscription
    {
        $id = $data->getTargetId() ?? throw new RuntimeException();
        $subscription = $this->entityManager->getRDBRepositoryByClass(Subscription::class)->getById($id);

        if (!$subscription) {
            throw new RuntimeException();
        }

        return $subscription;
    }
}
