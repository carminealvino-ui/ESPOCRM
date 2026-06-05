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

namespace Espo\Modules\Sales\Classes\Jobs;

use Espo\Core\Field\Date;
use Espo\Core\Job\JobDataLess;
use Espo\Core\Utils\Config;
use Espo\Modules\Sales\Tools\Inventory\Detach\Detach;
use Espo\Modules\Sales\Tools\Inventory\Detach\Params;

/**
 * @noinspection PhpUnused
 */
class InventoryDetach implements JobDataLess
{
    private const DEFAULT_PERIOD = '3 months';

    public function __construct(
        private Detach $detach,
        private Config $config
    ) {}

    public function run(): void
    {
        $period = $this->config->get('inventoryDetachJobPeriod') ?? self::DEFAULT_PERIOD;
        $date = Date::createToday()->modify('-' . $period);

        $params = new Params($date);

        $this->detach->run($params);
    }
}
