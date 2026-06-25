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
use Espo\ORM\EntityManager;
use Exception;

class CreateMissing
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
        private Util $util,
        private EntityManager $entityManager,
        private Log $log,
        private CreateMissingForSubscription $createMissingForSubscription,
    ) {}

    public function run(): void
    {
        $today = $this->util->getToday();

        $subscriptions = $this->subscriptionRepository->findWithMissingPeriod($today);

        foreach ($subscriptions as $subscription) {
            try {
                $this->entityManager
                    ->getTransactionManager()
                    ->run(fn () => $this->createMissingForSubscription->process($subscription, $today));
            } catch (Exception|Error $e) {
                $this->log->critical("Error while creating missing period for Subscription {id}.", [
                    'id' => $subscription->getId(),
                    'exception' => $e,
                ]);
            }
        }
    }
}
