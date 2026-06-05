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

namespace Espo\Modules\Sales\Classes\EntityCurrencyConverters;

use Espo\Core\Currency\Rates;
use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\Sales\Entities\PaymentEntry;
use Espo\ORM\Entity;
use Espo\Tools\Currency\Conversion\DefaultEntityConverter;
use Espo\Tools\Currency\Conversion\EntityConverter;

/**
 * @implements EntityConverter<PaymentEntry>
 */
class PaymentEntryConverter implements EntityConverter
{
    public function __construct(
        private DefaultEntityConverter $defaultEntityConverter,
    ) {}

    public function convert(Entity $entity, string $targetCurrency, Rates $rates): void
    {
        if ($entity->isLocked()) {
            return;
        }

        if ($entity->getAllocations() !== []) {
            throw Forbidden::createWithBody(
                'Cannot convert if payment with allocations.',
                Body::create()->withMessageTranslation('cannotConvertWithAllocations', 'PaymentEntry')
            );
        }

        $this->defaultEntityConverter->convert($entity, $targetCurrency, $rates);
    }
}
