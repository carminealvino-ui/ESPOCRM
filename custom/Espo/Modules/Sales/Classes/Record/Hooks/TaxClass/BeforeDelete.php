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

namespace Espo\Modules\Sales\Classes\Record\Hooks\TaxClass;

use Espo\Core\Record\DeleteParams;
use Espo\Core\Record\Hook\DeleteHook;
use Espo\Modules\Sales\Entities\TaxClass;
use Espo\Modules\Sales\Tools\TaxItemRule\ValidationHelper;
use Espo\ORM\Entity;

/**
 * @implements DeleteHook<TaxClass>
 */
class BeforeDelete implements DeleteHook
{
    public function __construct(
        private ValidationHelper $validationHelper,
    ) {}

    public function process(Entity $entity, DeleteParams $params): void
    {
        $this->validationHelper->validateDelete($entity);
    }
}
