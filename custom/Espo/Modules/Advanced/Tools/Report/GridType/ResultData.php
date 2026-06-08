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
 * License ID: 3fed3a66185f29dcda0e14c6ee94bf07
 ************************************************************************************/

namespace Espo\Modules\Advanced\Tools\Report\GridType;

use Espo\Modules\Advanced\Tools\Report\GridType\Data\Column;
use Espo\Modules\Advanced\Tools\Report\GridType\Data\Group;
use Espo\Modules\Advanced\Tools\Report\GridType\Data\Order;
use Espo\Modules\Advanced\Tools\Report\GridType\Data\SwitchItem;

readonly class ResultData
{
    /**
     * @param Column[] $columns
     * @param ?SwitchItem[] $switchItems
     * @param Order[] $orders,
     */
    public function __construct(
        public string $entityType,
        public Group $group,
        public array $columns,
        public ?Group $secondGroup = null,
        public array $orders = [],
        public ?string $currency = null,
        public ?string $chartType = null,
        public ?array $switchItems = null,
        public bool $noSubReport = false,
        public ?string $tableMode = null,
    ) {}
}
