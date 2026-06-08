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

use Espo\Modules\Sales\Tools\Subscription\Control\ActivateScheduled;
use Espo\Modules\Sales\Tools\Subscription\Control\CancelScheduled;
use Espo\Modules\Sales\Tools\Subscription\Control\ControlStatus;
use Espo\Modules\Sales\Tools\Subscription\Control\CreateFirstPeriod;
use Espo\Modules\Sales\Tools\Subscription\Control\EndActive;

class ControlService
{
    public function __construct(
        private EndActive $endActive,
        private CancelScheduled $cancelScheduled,
        private ActivateScheduled $activateScheduled,
        private CreateUpcoming $createUpcoming,
        private ControlStatus $controlStatus,
        private CreateMissing $createMissing,
        private CreateFirstPeriod $createFirstPeriod,
    ) {}

    public function control(): void
    {
        $this->endActive->run();
        $this->cancelScheduled->run();
        $this->activateScheduled->run();
        $this->createFirstPeriod->run();
        $this->createUpcoming->run();
        $this->createMissing->run();
        $this->controlStatus->run();
    }
}
