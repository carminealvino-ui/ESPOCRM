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
use Espo\Modules\Sales\Entities\SubscriptionPeriod as Period;
use Espo\Modules\Sales\Tools\Subscription\PeriodRepository;
use Espo\Modules\Sales\Tools\Subscription\Util;
use Espo\ORM\EntityManager;
use Exception;

class CancelScheduled
{
    public function __construct(
        private CancelHelper $cancelHelper,
        private Util $util,
        private PeriodRepository $periodRepository,
        private EntityManager $entityManager,
    ) {}

    public function run(): void
    {
        $today = $this->util->getToday();

        $periods = $this->periodRepository->findScheduledEndingBeforeDate($today);

        foreach ($periods as $period) {
            if ($period->getBillingStatus() === Period::BILLING_STATUS_INVOICED) {
                $period->setStatus(Period::STATUS_ENDED);
            } else {
                $period->setStatus(Period::STATUS_CANCELED);
            }

            try {
                $this->entityManager->saveEntity($period);
            } catch (Exception|Error $e) {
                $this->cancelHelper->cancelPeriodAfterSaveException($period, $e);
            }
        }
    }
}
