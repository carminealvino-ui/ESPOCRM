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

namespace Espo\Modules\Sales\Tools\Quote;

use Espo\Core\Currency\CalculatorUtil;
use Espo\Core\Field\Currency;
use Espo\Core\Utils\Config\SystemConfig;

class RoundingUtil
{
    public const FACTOR_PRECISE = '0.00000001';

    public function __construct(
        private PrecisionProvider $precisionProvider,
        private SystemConfig $systemConfig,
    ) {}

    /**
     * @param numeric-string $factor
     */
    public static function isFactorCourserThanPrecision(string $factor, int $precision): bool
    {
        $divider = '1';

        for ($i = 0; $i < $precision; $i ++) {
            $divider = CalculatorUtil::multiply($divider, '10');
        }

        $precisionFactor = CalculatorUtil::divide('1', $divider);

        return CalculatorUtil::compare($factor, $precisionFactor) > 0;
    }

    public function getPrecision(string $code): int
    {
        return $this->precisionProvider->get($code);
    }

    /**
     * @param ?numeric-string $factor
     */
    public function round(Currency $currency, ?string $factor = null): Currency
    {
        if ($factor !== null) {
            // String used to be converted to float.
            if (version_compare($this->systemConfig->getVersion(), '9.3.0') < 0) {
                $amount = CalculatorUtil::divide($currency->getAmountAsString(), $factor);
                $amount = CalculatorUtil::round($amount);
                $amount = CalculatorUtil::multiply($amount, $factor);

                return new Currency($amount, $currency->getCode());
            }

            return $currency->divide($factor)->round()->multiply($factor);
        }

        return $currency->round($this->getPrecision($currency->getCode()));
    }

    /**
     * @param numeric-string $amount
     * @param string $code
     * @return numeric-string
     */
    public function roundAmount(string $amount, string $code): string
    {
        return $this->round(Currency::create($amount, $code))->getAmountAsString();
    }
}
