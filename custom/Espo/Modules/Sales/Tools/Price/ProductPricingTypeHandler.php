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

namespace Espo\Modules\Sales\Tools\Price;

use Espo\Core\Currency\Converter;
use Espo\Core\Utils\Config;
use Espo\Modules\Sales\Entities\Product;

class ProductPricingTypeHandler
{
    public function __construct(
        private Config $config,
        private Converter $converter
    ) {}

    public function handle(Product $entity): void
    {
        if (!$entity->has('pricingType')) {
            return;
        }

        $pricingType = $entity->getPricingType();
        $precision = $this->config->get('currencyDecimalPlaces') ?? 0;

        if ($pricingType === Product::PRICING_TYPE_FIXED || !$pricingType) {
            return;
        }

        $listPrice = $entity->getListPrice();
        $costPrice = $entity->getCostPrice();
        $factor = $entity->getPricingFactor() ?? 0.0;

        if ($pricingType === Product::PRICING_TYPE_SAME_AS_LIST) {
            $entity->setUnitPrice($listPrice);

            return;
        }

        if ($pricingType === Product::PRICING_TYPE_DISCOUNT_FROM_LIST) {
            if (!$listPrice) {
                $entity->setUnitPrice(null);

                return;
            }

            $unitPrice = $listPrice
                ->subtract($listPrice->multiply($factor / 100.0))
                ->round($precision);

            $entity->setUnitPrice($unitPrice);

            return;
        }

        if (!$costPrice) {
            $entity->setUnitPrice(null);

            return;
        }

        $currency = $listPrice?->getCode() ?? $costPrice->getCode();

        if ($pricingType === Product::PRICING_TYPE_MARKUP_OVER_COST) {
            $unitPrice = $costPrice->add($costPrice->multiply($factor / 100.0));

            $unitPrice = $this->converter->convert($unitPrice, $currency)
                ->round($precision);

            $entity->setUnitPrice($unitPrice);

            return;
        }

        if ($pricingType === Product::PRICING_TYPE_PROFIT_MARGIN) {
            $unitPrice = $costPrice->divide(1 - $factor / 100.0);

            $unitPrice = $this->converter->convert($unitPrice, $currency)
                ->round($precision);

            $entity->setUnitPrice($unitPrice);
        }
    }
}
