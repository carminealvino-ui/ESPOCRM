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
use Espo\Core\Field\Currency;
use Espo\Core\Utils\Config;
use Espo\Modules\Sales\Entities\PriceRule;

class RuleCalculator
{
    public function __construct(
        private Converter $converter,
        private Config $config,
    ) {}

    public function calculate(Currency $base, PriceRule $rule): Currency
    {
        $precision = $this->config->get('currencyDecimalPlaces') ?? 2;

        $discountAmount = $base
            ->multiply($rule->getDiscount() / 100.0)
            ->round($precision);

        $price = $base->subtract($discountAmount);

        $method = $rule->getRoundingMethod();
        $factor = $rule->getRoundingFactor();
        $surcharge = $rule->getSurcharge();
        $currency = $rule->getCurrency() ?? $price->getCode();

        if ($currency !== $price->getCode()) {
            $price = $this->converter->convert($price, $currency);
        }

        $price = $this->round($price, $method, $factor);

        if ($surcharge) {
            $price = $price->add(new Currency($surcharge, $currency));
        }

        return $price->round($precision);
    }

    private function round(Currency $price, string $method, float $factor): Currency
    {
        if ($factor === 0.0) {
            $factor = 1.0;
        }

        $amount = $this->roundToFloat($method, $price, $factor);

        return new Currency($amount, $price->getCode());
    }

    private function roundToFloat(string $method, Currency $price, float $factor): float
    {
        if ($method === PriceRule::ROUNDING_METHOD_UP) {
            return ceil($price->getAmount() / $factor) * $factor;
        }

        if ($method === PriceRule::ROUNDING_METHOD_DOWN) {
            return floor($price->getAmount() / $factor) * $factor;
        }

        return round($price->getAmount() / $factor) * $factor;
    }
}
