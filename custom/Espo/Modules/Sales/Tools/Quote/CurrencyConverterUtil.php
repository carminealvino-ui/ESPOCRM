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
use Espo\Modules\Sales\Entities\PaymentEntry;
use Espo\Modules\Sales\Tools\Sales\HavingCurrencyRateEntity;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use LogicException;

class CurrencyConverterUtil
{
    public function __construct(
        private RoundingUtil $roundingUtil,
    ) {}

    /**
     * @param numeric-string $rate
     */
    public function convert(Currency $value, string $targetCode, string $rate, bool $round = true): Currency
    {
        $string = CalculatorUtil::multiply($value->getAmountAsString(), $rate);

        $value = Currency::create($string, $targetCode);

        if (!$round) {
            return $value;
        }

        return $this->roundingUtil->round($value);
    }

    public function convertToLocal(
        Currency $value,
        (OrderEntity & HavingCurrencyRateEntity) | PaymentEntry $order,
        bool $round = true
    ): Currency {

        $rate = $order->getCurrencyRate() ?? throw new LogicException("No currency rate.");
        $targetCode = $order->getLocalCurrency() ?? throw new LogicException("No local currency.");
        $sourceCode = $order->getAmountCurrency() ?? throw new LogicException("No currency code.");

        if ($sourceCode !== $value->getCode()) {
            throw new LogicException("Currency code mismatch.");
        }

        return $this->convert($value, $targetCode, $rate, $round);
    }

    public function convertFromLocal(
        Currency $value,
        OrderEntity & HavingCurrencyRateEntity $order,
        bool $round = true,
    ): Currency {

        $rate = $order->getCurrencyRate() ?? throw new LogicException("No currency rate.");
        $sourceCode = $order->getLocalCurrency() ?? throw new LogicException("No local currency.");
        $targetCode = $order->getAmountCurrency() ?? throw new LogicException("No currency code.");

        if ($sourceCode !== $value->getCode()) {
            throw new LogicException("Currency code mismatch.");
        }

        $string = CalculatorUtil::divide($value->getAmountAsString(), $rate);

        $value = Currency::create($string, $targetCode);

        if (!$round) {
            return $value;
        }

        return $this->roundingUtil->round($value);
    }
}
