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

namespace Espo\Modules\Sales\Hooks\SubscriptionUpdate;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Core\Job\Job\Data;
use Espo\Core\Job\JobSchedulerFactory;
use Espo\Core\Job\QueueName;
use Espo\Modules\Sales\Entities\Subscription;
use Espo\Modules\Sales\Entities\SubscriptionUpdate;
use Espo\Modules\Sales\Tools\Subscription\Jobs\UpdateSubscriptionBillingStatus;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;
use ReflectionClass;

/**
 * @implements AfterSave<SubscriptionUpdate>
 */
class UpdateSubscription implements AfterSave
{
    public static int $order = 20;

    public function __construct(
        private JobSchedulerFactory $jobSchedulerFactory,
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if (!$entity->isAttributeChanged(SubscriptionUpdate::FIELD_BILLING_STATUS)) {
            return;
        }

        $subscription = $entity->getSubscription();

        $toProcess =
            (
                $entity->getBillingStatus() !== SubscriptionUpdate::BILLING_STATUS_INVOICED &&
                $subscription->getBillingState() !== Subscription::BILLING_STATE_CLEAR
            ) ||
            (
                $entity->getBillingStatus() === SubscriptionUpdate::BILLING_STATUS_INVOICED &&
                $subscription->getBillingState() === Subscription::BILLING_STATE_CLEAR
            );

        if (!$toProcess) {
            return;
        }

        $queue = (new ReflectionClass(QueueName::class))->hasConstant('M0') ?
            QueueName::M0 : null;

        $this->jobSchedulerFactory
            ->create()
            ->setClassName(UpdateSubscriptionBillingStatus::class)
            ->setData(
                Data::create()
                    ->withTargetId($subscription->getId())
                    ->withTargetType($subscription->getEntityType())
            )
            ->setQueue($queue)
            ->schedule();
    }
}
