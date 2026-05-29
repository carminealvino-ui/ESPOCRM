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

namespace Espo\Modules\Sales\Tools\Price\MassUpdate;

use Espo\Modules\Sales\Entities\PriceRule;
use Espo\Modules\Sales\Entities\ProductPrice;
use Espo\Modules\Sales\Entities\SupplierProductPrice;
use Espo\Modules\Sales\Tools\Price\RuleCalculator;
use Espo\ORM\Entity;

/**
 * @implements PriceSetter<ProductPrice|SupplierProductPrice>
 */
class ProductPriceSetter implements PriceSetter
{
    public function __construct(
        private RuleCalculator $ruleCalculator
    ) {}

    public function set(Entity $entity, PriceRule $rule, ?string $field): void
    {
        $price = $this->ruleCalculator->calculate($entity->getPrice(), $rule);

        $entity->setPrice($price);
    }
}
