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
use Espo\Core\Field\Currency;
use Espo\Modules\Sales\Entities\TaxCode;
use Espo\Modules\Sales\Tools\Quote\RoundingUtil;
use Espo\Modules\Sales\Tools\Sales\HavingTaxInclusiveOrder;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\ORM\EntityCollection;
use RuntimeException;
use Traversable;

class TaxCalculator
{
    public function __construct(
        private RoundingUtil $roundingUtil,
    ) {}

    /**
     * @param numeric-string $quantity
     */
    public function prepare(
        Currency $unit,
        string $quantity,
        TaxCode $taxCode,
        OrderEntity $order,
    ): TaxCalculationResult {

        $taxCodes = $this->getTaxCodes($taxCode);
        $isTaxInclusive = $order instanceof HavingTaxInclusiveOrder && $order->isTaxInclusive();
        $inclusiveTaxCodes = $this->getInclusiveTaxCodes($taxCodes, $isTaxInclusive);
        $hasInclusiveTotalLevelRounding = PriceInclusiveHelper::hasInclusiveTotalLevelRounding($inclusiveTaxCodes);
        $hasGrandRounding = $order instanceof RoundingHavingOrder && $order->getRoundingProfile();
        $skipDiffCorrection = $hasInclusiveTotalLevelRounding || $hasGrandRounding;

        $gross = $unit->multiply($quantity);
        $gross = $this->roundingUtil->round($gross);

        $net = $this->obtainNet($inclusiveTaxCodes, $gross);

        $total = $net;
        $previous = Currency::create('0', $net->getCode());
        $taxAmount = Currency::create('0', $net->getCode());

        $items = [];

        foreach ($taxCodes as $i => $itemCode) {
            if ($itemCode->getType() === TaxCodeType::Composite) {
                throw new RuntimeException("Cannot use composite tax inside a composite tax.");
            }

            $item = $this->calculateForItemSubItem(
                net: $net,
                total: $total,
                previous: $previous,
                taxCode: $itemCode,
                quantity: $quantity,
            );

            if ($isTaxInclusive || $i < count($inclusiveTaxCodes)) {
                $item = $item->withIsInPrice(true);
            }

            $items[] = $item;

            if (!$skipDiffCorrection && $i === count($inclusiveTaxCodes) - 1) {
                $diff = $gross->subtract($total);

                for ($j = $i; $j >= 0; $j--) {
                    if ($inclusiveTaxCodes[$j]->getType() !== TaxCodeType::Percentage) {
                        continue;
                    }

                    $items[$j] = $items[$j]->withAddedAmount($diff);

                    break;
                }

                $total = $total->add($diff);
                $previous = $previous->add($diff);
                $taxAmount = $taxAmount->add($diff);
            }

            // @todo Revise if tax amount return is needed.
            $taxAmount = $taxAmount->add($items[$i]->amount);
        }

        $unitNet = $unit;
        $lineAmount = null;
        $roundingAmount = null;

        if ($inclusiveTaxCodes !== []) {
            $unitNet = CalculatorUtil::compare($quantity, '0') !== 0 ?
                $net->divide($quantity) : $net;

            $unitNet = $this->roundingUtil->round($unitNet);

            $lineAmount = $net;
        }

        return new TaxCalculationResult(
            items: $items,
            taxAmount: $taxAmount,
            unitNet: $unitNet,
            lineAmount: $lineAmount,
            roundingAmount: $roundingAmount,
        );
    }

    /**
     * @return Traversable<int, TaxCode>
     */
    private function getTaxCodes(TaxCode $taxCode): Traversable
    {
        if ($taxCode->getType() === TaxCodeType::Composite) {
            return $taxCode->getItemCollection();
        }

        return new EntityCollection([$taxCode]);
    }

    /**
     * @param numeric-string $quantity
     */
    private function calculateForItemSubItem(
        Currency $net,
        Currency &$total,
        Currency &$previous,
        TaxCode $taxCode,
        string $quantity,
    ): CalculationItem {

        if ($taxCode->getType() === TaxCodeType::Composite) {
            throw new RuntimeException("Group tax code cannot have group tax code items.");
        }

        $base = $net;

        if ($taxCode->getType() === TaxCodeType::Percentage) {
            $base = match ($taxCode->getBase()) {
                TaxCodeBase::NetAmount => $net,
                TaxCodeBase::CumulativeTotal => $total,
                TaxCodeBase::PreviousTax => $previous,
            };
        }

        $tax = $this->calculateItemAmount(
            taxCode: $taxCode,
            base: $base,
            quantity: $quantity,
        );

        $taxPrecise = $tax;
        $taxPrecise = $this->roundingUtil->round($taxPrecise, RoundingUtil::FACTOR_PRECISE);

        $tax = $this->roundingUtil->round($tax);

        $total = $total->add($tax);
        $previous = $tax;

        $rate = null;

        if ($taxCode->getType() === TaxCodeType::Percentage) {
            $rate = $taxCode->getRate();
        }

        return new CalculationItem(
            amount: $tax,
            baseAmount: $base,
            taxCode: $taxCode,
            amountPrecise: $taxPrecise,
            rate: $rate,
        );
    }

    /**
     * @param numeric-string $quantity
     */
    private function calculateItemAmount(
        TaxCode $taxCode,
        Currency $base,
        string $quantity,
    ): Currency {
        if ($taxCode->getType() === TaxCodeType::Percentage) {
            $rate = $taxCode->getRate() ?? '0';

            $tax = $base->multiply($rate)->divide('100');
        } else {
            $amount = $taxCode->getAmount() ?? '0';

            $tax = Currency::create($amount, $base->getCode());
            $tax = $tax->multiply($quantity);
        }

        return $tax;
    }

    /**
     * @param Traversable<int, TaxCode> $taxCodes
     * @return TaxCode[]
     */
    private function getInclusiveTaxCodes(Traversable $taxCodes, bool $isTaxInclusive): array
    {
        if ($isTaxInclusive) {
            return iterator_to_array($taxCodes);
        }

        $list = [];

        foreach ($taxCodes as $taxCode) {
            if (!$taxCode->isIncludedInPrice()) {
                break;
            }

            $list[] = $taxCode;
        }

        return $list;
    }

    /**
     * @param TaxCode[] $inclusiveTaxCodes
     */
    private function obtainNet(array $inclusiveTaxCodes, Currency $gross): Currency
    {
        $netRaw = PriceInclusiveHelper::computeNet($inclusiveTaxCodes, $gross->getAmountAsString());

        $net = Currency::create($netRaw, $gross->getCode());

        return $this->roundingUtil->round($net);
    }
}
