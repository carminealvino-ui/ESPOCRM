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

use Espo\Core\Currency\CalculatorUtil as Calc;
use Espo\Core\Field\Currency;
use Espo\Modules\Sales\Entities\CreditNote;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Entities\PurchaseOrder;
use Espo\Modules\Sales\Entities\Quote;
use Espo\Modules\Sales\Entities\ReturnOrder;
use Espo\Modules\Sales\Entities\SalesOrder;
use Espo\Modules\Sales\Entities\SupplierBill;
use Espo\Modules\Sales\Entities\SupplierCredit;
use Espo\Modules\Sales\Entities\Tax;
use Espo\Modules\Sales\Entities\TaxCode;
use Espo\Modules\Sales\Entities\TaxLineItem;
use Espo\Modules\Sales\Tools\Sales\ConfigDataProvider;
use Espo\Modules\Sales\Tools\Sales\HavingTaxInclusiveOrder;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\Modules\Sales\Tools\Tax\CalculationItem;
use Espo\Modules\Sales\Tools\Tax\PriceInclusiveHelper;
use Espo\Modules\Sales\Tools\Tax\RoundingHavingOrder;
use Espo\Modules\Sales\Tools\Tax\ShippingProrationItem;
use Espo\Modules\Sales\Tools\Tax\TaxCalculator;
use Espo\Modules\Sales\Tools\Tax\TaxCodeType;
use Espo\Modules\Sales\Tools\Tax\TaxLineSaveItem;
use Espo\Modules\Sales\Tools\Tax\TaxRoundingLevel;
use Espo\ORM\EntityManager;
use LogicException;

class ShippingSaveProcessor
{
    public function __construct(
        private ConfigDataProvider $configDataProvider,
        private ProcessorHelper $helper,
        private EntityManager $entityManager,
        private TaxCalculator $taxCalculator,
        private RoundingUtil $roundingUtil,
    ) {}

    /**
     * @return numeric-string
     */
    public function process(
        Quote|SalesOrder|Invoice|CreditNote|PurchaseOrder|ReturnOrder|SupplierBill|SupplierCredit $order,
    ): string {

        $shippingCost = $order->getShippingCost() ?? $this->helper->createZero($order);

        $shippingAmount = $shippingCost;

        $order->setShippingAmount($shippingAmount);

        if (ProcessorHelper::isZero($shippingCost)) {
            return '0';
        }

        $mode = $order->getShippingTaxMode();
        $amount = self::getAmountString($order);

        if (
            $mode === Tax::SHIPPING_MODE_FIXED ||
            $mode === Tax::SHIPPING_MODE_PROPORTIONAL && ProcessorHelper::isZero($amount)
        ) {
            return $this->processTaxFixed($order);
        }

        if ($mode === Tax::SHIPPING_MODE_PROPORTIONAL) {
            return $this->processTaxProportional($order);
        }

        return '0';
    }

    /**
     * @return numeric-string
     */
    private function processTaxFixed(
        Quote|SalesOrder|Invoice|CreditNote|PurchaseOrder|ReturnOrder|SupplierBill|SupplierCredit $order,
    ): string {

        if ($this->configDataProvider->isTaxCodesEnabled()) {
            return $this->processTaxFixedWithTaxCode($order);
        }

        return $this->processTaxFixedWithoutTaxCode($order);
    }

    /**
     * @return numeric-string
     */
    private function processTaxFixedWithTaxCode(
        ReturnOrder|CreditNote|Invoice|SalesOrder|PurchaseOrder|Quote|SupplierBill|SupplierCredit $order,
    ): string {

        $taxCodeId = $order->getTax()?->getShippingTaxCodeLink()?->getId();

        if (!$taxCodeId) {
            return '0';
        }

        $taxCode = $this->helper->getTaxCode($taxCodeId);

        $cost = $order->getShippingCost() ?? throw new LogicException();

        $calculation = $this->taxCalculator->prepare(
            unit: $cost,
            quantity: '1',
            taxCode: $taxCode,
            order: $order,
        );

        if ($calculation->lineAmount !== null) {
            $order->setShippingAmount($calculation->lineAmount);
        }

        $total = Currency::create('0', $cost->getCode());

        foreach ($calculation->items as $calculatedItem) {
            $taxLineItem = $this->entityManager->getRDBRepositoryByClass(TaxLineItem::class)->getNew();

            $taxLineItem
                ->setComponent(TaxLineItem::COMPONENT_SHIPPING)
                ->setTaxCode($taxCode)
                ->setRate($calculatedItem->rate);

            $this->helper->setTaxLineItemAmounts($taxLineItem, $calculatedItem, $order);

            $order->addTaxLineSaveItem(
                new TaxLineSaveItem(
                    taxLineItem: $taxLineItem,
                    isInPrice: $calculatedItem->isInPrice,
                )
            );

            $total = $total->add($calculatedItem->amount);
        }

        return $total->getAmountAsString();
    }

    /**
     * @return ShippingProrationItem[]
     */
    private function getItemsForProportionalTax(
        ReturnOrder|CreditNote|Invoice|Quote|PurchaseOrder|SalesOrder|SupplierBill|SupplierCredit $order,
    ): array {

        $taxCodes = [];

        /** @var array<string, TaxLineItem[]> $map */
        $map = [];

        foreach ($this->getTaxLineItemsForProportional($order) as $lineItem) {
            $taxCode = $lineItem->getTaxCode();

            if (!array_key_exists($taxCode->getId(), $map)) {
                $taxCodes[] = $taxCode;
            }

            $map[$taxCode->getId()] = array_merge(
                $map[$taxCode->getId()] ?? [],
                [$lineItem]
            );
        }

        usort($taxCodes, function (TaxCode $a, TaxCode $b) {
            if ($a->isIncludedInPrice() && !$b->isIncludedInPrice()) {
                return -1;
            }

            if (!$a->isIncludedInPrice() && $b->isIncludedInPrice()) {
                return 1;
            }

            return $a->getOrder() - $b->getOrder();
        });

        $amount = '0';

        foreach ($taxCodes as $taxCode) {
            $lineItems = $map[$taxCode->getId()] ?? throw new LogicException();

            foreach ($lineItems as $lineItem) {
                $amount = Calc::add($amount, $lineItem->getBaseAmount()->getAmountAsString());
            }
        }

        $output = [];

        foreach ($taxCodes as $taxCode) {
            $lineItems = $map[$taxCode->getId()] ?? throw new LogicException();

            $currencyCode = $this->helper->getCurrency($order);

            $baseAmount = Currency::create('0', $currencyCode);

            foreach ($lineItems as $lineItem) {
                $baseAmount = $baseAmount->add($lineItem->getBaseAmount());
            }

            $portion = Calc::divide(
                $baseAmount->getAmountAsString(),
                $amount
            );

            $output[] = new ShippingProrationItem(
                taxCode: $taxCode,
                baseAmount: $baseAmount,
                portion: $portion,
            );
        }

        return $output;
    }

    /**
     * @return numeric-string
     */
    private function processTaxProportional(
        ReturnOrder|CreditNote|Invoice|Quote|PurchaseOrder|SalesOrder|SupplierBill|SupplierCredit $order,
    ): string {

        $amount = ProcessorHelper::getAmountString($order);

        if (ProcessorHelper::isZero($amount)) {
            return '0';
        }

        if ($this->configDataProvider->isTaxCodesEnabled()) {
            return $this->processTaxProportionalWithTaxCodes($order);
        }

        return $this->processTaxProportionalWithoutCodes($order);
    }

    /**
     * @return numeric-string
     */
    private function processTaxProportionalWithTaxCodes(
        ReturnOrder|CreditNote|Invoice|Quote|PurchaseOrder|SalesOrder|SupplierBill|SupplierCredit $order,
    ): string {

        $prorationItems = $this->getItemsForProportionalTax($order);
        $inclusiveItems = $this->getPriceInclusiveItems($prorationItems, $order);
        $hasInclusiveTotalLevelRounding = $this->hasInclusiveTotalLevelRounding($inclusiveItems);
        $hasGrandRounding = $order instanceof RoundingHavingOrder && $order->getRoundingProfile();
        $skipDiffCorrection = $hasInclusiveTotalLevelRounding || $hasGrandRounding;

        $isTaxInclusive = $order instanceof HavingTaxInclusiveOrder && $order->isTaxInclusive();

        if ($inclusiveItems !== []) {
            $this->computeNetForInclusiveProportional($inclusiveItems, $order);
        }

        $net = $order->getShippingAmount() ?? throw new LogicException();
        $gross = $order->getShippingCost() ?? throw new LogicException();

        $newGross = $net;
        $calculatedItems = [];

        $maxI = null;
        $maxValue = null;

        foreach ($prorationItems as $i => $item) {
            $calculatedItem = $this->calculateTaxProportionalForTaxCode($item, $order);

            if ($isTaxInclusive || $i < count($inclusiveItems)) {
                $calculatedItem = $calculatedItem->withIsInPrice(true);
            }

            $calculatedItems[] = $calculatedItem;

            if ($i >= count($inclusiveItems)) {
                continue;
            }

            if ($maxValue === null || $calculatedItem->amount->compare($maxValue) > 0) {
                $maxI = $i;
                $maxValue = $calculatedItem->amount;
            }

            $newGross = $newGross->add($calculatedItem->amount);
        }

        $diff = $gross->subtract($newGross);

        if (
            !$skipDiffCorrection &&
            $inclusiveItems !== [] &&
            $maxI !== null &&
            array_key_exists($maxI, $calculatedItems)
        ) {
            $calculatedItems[$maxI] = $calculatedItems[$maxI]->withAddedAmount($diff);
        }

        $outputTotal = '0';

        foreach ($calculatedItems as $calculatedItem) {
            $this->addProportionalItem($calculatedItem, $order);

            $outputTotal = Calc::add($outputTotal, $calculatedItem->amount->getAmountAsString());
        }

        return $outputTotal;
    }

    /**
     * @return TaxLineItem[]
     */
    private function getTaxLineItemsForProportional(
        ReturnOrder|CreditNote|Invoice|Quote|PurchaseOrder|SalesOrder|SupplierBill|SupplierCredit $order,
    ): array {

        $saveItems = array_filter($order->getTaxLineSaveItems(), function ($item) {
            $taxCode = $item->taxLineItem->getTaxCode();

            return $taxCode->getType() === TaxCodeType::Percentage &&
                $taxCode->applyToProportionalShipping();
        });

        $saveItems = array_values($saveItems);

        return array_map(fn ($it) => $it->taxLineItem, $saveItems);
    }

    private function calculateTaxProportionalForTaxCode(
        ShippingProrationItem $item,
        ReturnOrder|CreditNote|Invoice|Quote|PurchaseOrder|SalesOrder|SupplierBill|SupplierCredit $order,
    ): CalculationItem {

        $rate = $item->taxCode->getRate() ?? throw new LogicException("No rate.");

        $base = $this->calculateProportionalItemBase(
            order: $order,
            item: $item,
        );

        $taxPrecise = $base->multiply($rate)->divide('100');
        $taxPrecise = $this->roundingUtil->round($taxPrecise, RoundingUtil::FACTOR_PRECISE);

        $tax = $this->roundingUtil->round($taxPrecise);

        return new CalculationItem(
            amount: $tax,
            baseAmount: $base,
            taxCode: $item->taxCode,
            amountPrecise: $taxPrecise,
            rate: $rate,
        );
    }

    /**
     * @return numeric-string
     */
    private function processTaxProportionalWithoutCodes(
        ReturnOrder|CreditNote|Invoice|SalesOrder|PurchaseOrder|Quote|SupplierBill|SupplierCredit $order,
    ): string {

        $taxAmount = '0';
        $net = $order->getShippingCost()?->getAmountAsString() ?? throw new LogicException();

        $amount = self::getAmountString($order);

        if ($order instanceof HavingTaxInclusiveOrder && $order->isTaxInclusive()) {
            $pairs = [];

            foreach ($order->getItems() as $item) {
                /** @var numeric-string $itemAmount */
                $itemAmount = (string) ($item->get(OrderEntity::FIELD_AMOUNT) ?? '0');
                /** @var numeric-string $itemRate */
                $itemRate = (string) ($item->get(OrderEntity::FIELD_AMOUNT) ?? '0');

                $proportion = Calc::divide($itemAmount, $amount);
                $rateNorm = Calc::divide($itemRate, '100');

                $pairs[] = [$proportion, $rateNorm];
            }

            $net = PriceInclusiveHelper::computeNetProportional($pairs, $net);

            $order->setShippingAmount(Currency::create($net, $order->getShippingCost()->getCode()));
        }

        foreach ($order->getItems() as $item) {
            /** @var numeric-string $itemAmount */
            $itemAmount = (string) ($item->get(OrderEntity::FIELD_AMOUNT) ?? '0');
            /** @var numeric-string $itemRate */
            $itemRate = (string) ($item->get(OrderEntity::FIELD_AMOUNT) ?? '0');

            $itemTaxAmount = Calc::multiply(
                Calc::multiply(
                    $net,
                    Calc::divide($itemAmount, $amount)
                ),
                Calc::divide($itemRate, '100')
            );

            $itemTaxAmount = $this->roundAmount($itemTaxAmount, $order);

            $taxAmount = Calc::add($taxAmount, $itemTaxAmount);
        }

        return $taxAmount;
    }

    private function calculateProportionalItemBase(
        ReturnOrder|CreditNote|Invoice|Quote|PurchaseOrder|SalesOrder|SupplierBill|SupplierCredit $order,
        ShippingProrationItem $item,
    ): Currency {

        $net = $order->getShippingAmount()?->getAmountAsString() ?? '0';

        $base = Calc::multiply($net, $item->portion);
        $base = $this->roundAmount($base, $order);

        return Currency::create($base, $this->helper->getCurrency($order));
    }

    /**
     * @return numeric-string
     */
    private static function getAmountString(
        Quote|SalesOrder|Invoice|CreditNote|PurchaseOrder|ReturnOrder|SupplierBill|SupplierCredit $order
    ): string {

        return $order->getAmount()?->getAmountAsString() ?? '0';
    }

    /**
     * @param ShippingProrationItem[] $items
     * @return ShippingProrationItem[]
     */
    private function getPriceInclusiveItems(array $items, OrderEntity $order): array
    {
        $isTaxInclusive = $order instanceof HavingTaxInclusiveOrder && $order->isTaxInclusive();

        if ($isTaxInclusive) {
            return $items;
        }

        $priceInclusiveItems = [];

        foreach ($items as $it) {
            if (!$it->taxCode->isIncludedInPrice()) {
                break;
            }

            $priceInclusiveItems[] = $it;
        }

        return $priceInclusiveItems;
    }

    /**
     * @param ShippingProrationItem[] $priceInclusiveItems
     */
    private function computeNetForInclusiveProportional(
        array $priceInclusiveItems,
        ReturnOrder|CreditNote|Invoice|Quote|PurchaseOrder|SalesOrder|SupplierBill|SupplierCredit $order,
    ): void {

        $pais = array_map(function ($it) {
            $rateNorm = Calc::divide($it->taxCode->getRate() ?? '0', '100');

            return [$it->portion, $rateNorm];
        }, $priceInclusiveItems);

        $gross = $order->getShippingCost()?->getAmountAsString() ?? '0';

        $net = PriceInclusiveHelper::computeNetProportional($pais, $gross);

        $shippingAmount = Currency::create($net, $this->helper->getCurrency($order));

        $shippingAmount = $this->roundingUtil->round($shippingAmount);

        $order->setShippingAmount($shippingAmount);
    }

    /**
     * @param numeric-string $value
     * @return numeric-string
     */
    private function roundAmount(string $value, OrderEntity $order): string
    {
        return $this->roundingUtil->roundAmount($value, $this->helper->getCurrency($order));
    }

    /**
     * @param ReturnOrder|CreditNote|Invoice|SalesOrder|PurchaseOrder|Quote|SupplierBill|SupplierCredit $order
     * @return string
     */

    /**
     * @return numeric-string
     */
    private function processTaxFixedWithoutTaxCode(
        ReturnOrder|CreditNote|Invoice|SalesOrder|PurchaseOrder|Quote|SupplierBill|SupplierCredit $order,
    ): string {

        $rate = (string) ($order->getTaxRate() ?? '0');

        if (ProcessorHelper::isZero($rate) || !$order->getShippingCost()) {
            return '0';
        }

        $net = $order->getShippingCost()->getAmountAsString();

        if ($order instanceof HavingTaxInclusiveOrder && $order->isTaxInclusive()) {
            $net = PriceInclusiveHelper::obtainNetWithOneRate($net, $rate);
            $net = $this->roundAmount($net, $order);

            $order->setShippingAmount(Currency::create($net, $order->getShippingCost()->getCode()));
        }

        $taxAmount = Calc::multiply(
            $net,
            Calc::divide($rate, '100')
        );

        return $this->roundAmount($taxAmount, $order);
    }

    private function addProportionalItem(
        CalculationItem $calculatedItem,
        ReturnOrder|CreditNote|Invoice|Quote|PurchaseOrder|SalesOrder|SupplierBill|SupplierCredit $order,
    ): void {

        $taxLineItem = $this->entityManager->getRDBRepositoryByClass(TaxLineItem::class)->getNew();

        $taxLineItem
            ->setComponent(TaxLineItem::COMPONENT_SHIPPING)
            ->setTaxCode($calculatedItem->taxCode)
            ->setRate($calculatedItem->rate);

        $this->helper->setTaxLineItemAmounts($taxLineItem, $calculatedItem, $order);

        $order->addTaxLineSaveItem(
            new TaxLineSaveItem(
                taxLineItem: $taxLineItem,
                isInPrice: $calculatedItem->isInPrice,
            )
        );
    }

    /**
     * @param ShippingProrationItem[] $inclusiveItems
     */
    private function hasInclusiveTotalLevelRounding(array $inclusiveItems): bool
    {
        $hasInclusiveTotalLevelRounding = false;

        foreach ($inclusiveItems as $it) {
            if ($it->taxCode->getRoundingLevel() === TaxRoundingLevel::Total) {
                $hasInclusiveTotalLevelRounding = true;
            }
        }

        return $hasInclusiveTotalLevelRounding;
    }
}
