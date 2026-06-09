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

namespace Espo\Modules\Sales\Classes\FieldValidators\InventoryTransaction\Warehouse;

use Espo\Core\FieldValidation\Validator;
use Espo\Core\FieldValidation\Validator\Data;
use Espo\Core\FieldValidation\Validator\Failure;
use Espo\Modules\Sales\Entities\InventoryTransaction;
use Espo\Modules\Sales\Tools\Sales\ConfigDataProvider;
use Espo\ORM\Entity;

/**
 * @implements Validator<InventoryTransaction>
 */
class Required implements Validator
{
    public function __construct(
        private ConfigDataProvider $configDataProvider
    ) {}

    public function validate(Entity $entity, string $field, Data $data): ?Failure
    {
        if (!$this->configDataProvider->isWarehousesEnabled()) {
            return null;
        }

        if ($entity->getWarehouse()) {
            return null;
        }

        if (
            !in_array($entity->getType(), [
                InventoryTransaction::TYPE_RESERVE,
                InventoryTransaction::TYPE_TRANSFER,
            ])
        ) {
            return null;
        }

        return Failure::create();
    }
}
