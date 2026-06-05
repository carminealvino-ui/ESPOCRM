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

namespace Espo\Modules\Sales\Classes\FieldValidators\PaymentTermsProfile\Items;

use Espo\Core\Currency\CalculatorUtil;
use Espo\Core\FieldValidation\Validator;
use Espo\Core\FieldValidation\Validator\Data;
use Espo\Core\FieldValidation\Validator\Failure;
use Espo\Modules\Sales\Entities\PaymentTermsProfile;
use Espo\ORM\Entity;
use stdClass;

/**
 * @implements Validator<PaymentTermsProfile>
 */
class Valid implements Validator
{

    public function validate(Entity $entity, string $field, Data $data): ?Failure
    {
        if ($this->validateRaw($data)) {
            return Failure::create();
        }

        $sum = '0';

        foreach ($entity->getItems() as $item) {
            $sum = CalculatorUtil::add($sum, $item->percentage);
        }

        if (CalculatorUtil::compare($sum, '100') !== 0) {
            return Failure::create();
        }

        $previousDays = -1;

        foreach ($entity->getItems() as $item) {
            if ($item->days <= $previousDays) {
                return Failure::create();
            }

            $previousDays = $item->days;
        }

        return null;
    }

    private function validateRaw(Data $data): ?Failure
    {
        $raw = $data->get('items') ?? null;

        if (!is_array($raw)) {
            return Failure::create();
        }

        foreach ($raw as $it) {
            if (!$it instanceof stdClass) {
                return Failure::create();
            }

            $percentage = $it->percentage ?? null;
            $days = $it->days ?? null;

            if (!is_string($percentage) || !is_numeric($percentage)) {
                return Failure::create();
            }

            if (!is_int($days)) {
                return Failure::create();
            }
        }

        return null;
    }
}
