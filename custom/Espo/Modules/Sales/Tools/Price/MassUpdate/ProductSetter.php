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
use Espo\Modules\Sales\Entities\Product;
use Espo\Modules\Sales\Tools\Price\ProductPricingTypeHandler;
use Espo\Modules\Sales\Tools\Price\RuleCalculator;
use Espo\ORM\Entity;

/**
 * @implements PriceSetter<Product>
 */
class ProductSetter implements PriceSetter
{
    public function __construct(
        private RuleCalculator $ruleCalculator,
        private ProductPricingTypeHandler $handler
    ) {}

    public function set(Entity $entity, PriceRule $rule, ?string $field): void
    {
        $price = null;

        if ($field === 'costPrice') {
            $price = $entity->getCostPrice();
        } else if ($field === 'listPrice') {
            $price = $entity->getListPrice();
        } else if ($field === 'unitPrice') {
            $price = $entity->getUnitPrice();
        }

        if (!$price) {
            return;
        }

        $price = $this->ruleCalculator->calculate($price, $rule);

        if ($field === 'costPrice') {
            $entity->setCostPrice($price);
        } else if ($field === 'listPrice') {
            $entity->setListPrice($price);
        } else if ($field === 'unitPrice') {
            $entity->setUnitPrice($price);
        }

        $this->handler->handle($entity);
    }
}
