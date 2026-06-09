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

use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\Core\Utils\Log;
use Espo\Modules\Sales\Entities\SubscriptionPeriod as Period;
use Espo\ORM\EntityManager;
use Throwable;

class CancelHelper
{
    public function __construct(
        private EntityManager $entityManager,
        private Log $log,
    ) {}

    public function cancelPeriodAfterSaveException(Period $period, Throwable $exception): void
    {
        $this->log->critical("Could not change status for SubscriptionPeriod {id}.", [
            'id' => $period->getId(),
            'exception' => $exception,
        ]);

        $period->setStatus(Period::STATUS_CANCELED);

        $this->entityManager->saveEntity($period, [SaveOption::SKIP_HOOKS => true]);
    }
}
