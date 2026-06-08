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

namespace Espo\Modules\Sales\Entities;

use Espo\Core\ORM\Entity;
use UnexpectedValueException;

class PaymentMandateSepa extends Entity
{
    public const ENTITY_TYPE = 'PaymentMandateSepa';

    public const TYPE = 'Sepa';

    public const SCHEME_CORE = 'Core';
    public const SCHEME_B2B = 'B2B';

    public function getScheme(): string
    {
        $value = $this->get('scheme');

        if (!is_string($value)) {
            throw new UnexpectedValueException("No scheme.");
        }

        return $value;
    }
}
