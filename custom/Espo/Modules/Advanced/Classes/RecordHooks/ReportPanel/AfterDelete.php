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

namespace Espo\Modules\Advanced\Classes\RecordHooks\ReportPanel;

use Espo\Core\Record\DeleteParams;
use Espo\Core\Record\Hook\DeleteHook;
use Espo\Modules\Advanced\Entities\ReportPanel;
use Espo\Modules\Advanced\Tools\ReportPanel\Service;
use Espo\ORM\Entity;

/**
 * @implements DeleteHook<ReportPanel>
 */
class AfterDelete implements DeleteHook
{
    public function __construct(
        private Service $service,
    ) {}

    public function process(Entity $entity, DeleteParams $params): void
    {
        $this->service->rebuild($entity->getTargetEntityType());
    }
}
