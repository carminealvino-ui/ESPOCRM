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

namespace Espo\Modules\Sales\Tools\Tax;

use Espo\Core\Currency\CalculatorUtil;
use Espo\Modules\Sales\Entities\TaxCode;
use LogicException;

class PriceInclusiveHelper
{
    /**
     * @param TaxCode[] $taxCodes
     * @param numeric-string $gross
     * @return numeric-string
     */
    static public function computeNet(array $taxCodes, string $gross): string
    {
        if ($taxCodes === []) {
            return $gross;
        }

        $lines = self::breakdown($taxCodes);

        $a = '0';
        $b = '0';

        foreach ($lines as $line) {
            $a = CalculatorUtil::add($a, $line[0]);
            $b = CalculatorUtil::add($b, $line[1]);
        }

        $net = CalculatorUtil::divide(
            CalculatorUtil::subtract($gross, $b),
            CalculatorUtil::add('1', $a),
        );

        return self::trimNumber($net);
    }

    /**
     * @internal
     * @param TaxCode[] $taxCodes
     * @return array{numeric-string, numeric-string}[]
     */
    public static function breakdown(array $taxCodes): array
    {
        /** @var array{numeric-string, numeric-string}[] $lines */
        $lines = [];

        foreach ($taxCodes as $i => $taxCode) {
            if ($taxCode->getType() === TaxCodeType::Percentage) {
                $r = CalculatorUtil::divide($taxCode->getRate() ?? '0', '100');

                if ($taxCode->getBase() === TaxCodeBase::NetAmount) {
                    $a = $r;

                    $lines[] = [$a, '0'];

                    continue;
                }

                if ($taxCode->getBase() === TaxCodeBase::CumulativeTotal) {
                    $a = '1';
                    $b = '0';

                    for ($j = 0; $j < $i; $j++) {
                        $a = CalculatorUtil::add($a, $lines[$j][0]);
                        $b = CalculatorUtil::add($b, $lines[$j][1]);
                    }

                    $a = CalculatorUtil::multiply($a, $r);
                    $b = CalculatorUtil::multiply($b, $r);

                    $lines[] = [$a, $b];

                    continue;
                }

                if ($taxCode->getBase() === TaxCodeBase::PreviousTax) {
                    $pa = $lines[$i - 1][0] ?? '0';
                    $pb = $lines[$i - 1][1] ?? '0';

                    $a = CalculatorUtil::multiply($pa, $r);
                    $b = CalculatorUtil::multiply($pb, $r);

                    $lines[] = [$a, $b];

                    continue;
                }

                throw new LogicException();
            }

            if ($taxCode->getType() === TaxCodeType::Fixed) {
                $amount = $taxCode->getAmount() ?? '0';

                $a = '0';
                $b = $amount;

                $lines[] = [$a, $b];

                continue;
            }

            throw new LogicException();
        }

        foreach ($lines as $i => $line) {
            $lines[$i][0] = self::trimNumber($line[0]);
            $lines[$i][1] = self::trimNumber($line[1]);
        }

        return $lines;
    }

    /**
     * @param array{numeric-string, numeric-string}[] $pairs
     * @param numeric-string $gross
     * @return numeric-string
     */
    public static function computeNetProportional(array $pairs, string $gross): string
    {
        $a = '1';

        foreach ($pairs as $pair) {
            $c = CalculatorUtil::multiply($pair[0], $pair[1]);
            $a = CalculatorUtil::add($a, $c);
        }

        $net = CalculatorUtil::divide($gross, $a);

        return self::trimNumber($net);
    }

    /**
     * @param numeric-string $value
     * @return numeric-string
     */
    private static function trimNumber(string $value): string
    {
        if (!str_contains($value, '.')) {
            return $value;
        }

        $value = rtrim($value, '0');

        /** @var numeric-string */
        return rtrim($value, '.');
    }

    /**
     * @param numeric-string $gross A gross amount.
     * @param numeric-string $rate Percent.
     * @return numeric-string A net amount.
     */
    public static function obtainNetWithOneRate(string $gross, string $rate): string
    {
        return CalculatorUtil::divide(
            $gross,
            CalculatorUtil::add('1', CalculatorUtil::divide($rate, '100'))
        );
    }

    /**
     * @param TaxCode[] $inclusiveTaxCodes
     */
    public static function hasInclusiveTotalLevelRounding(array $inclusiveTaxCodes): bool
    {
        foreach ($inclusiveTaxCodes as $inclusiveTaxCode) {
            if ($inclusiveTaxCode->getRoundingLevel() === TaxRoundingLevel::Total) {
                return true;
            }
        }

        return false;
    }
}
