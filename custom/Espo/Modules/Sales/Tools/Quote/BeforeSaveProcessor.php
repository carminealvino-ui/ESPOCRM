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
use Espo\Core\Utils\Metadata;
use Espo\Modules\Sales\Entities\CreditNote;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Entities\InvoiceItem;
use Espo\Modules\Sales\Entities\PaymentInstallment;
use Espo\Modules\Sales\Entities\PriceBook;
use Espo\Modules\Sales\Entities\Product;
use Espo\Modules\Sales\Entities\PurchaseOrder;
use Espo\Modules\Sales\Entities\Quote;
use Espo\Modules\Sales\Entities\QuoteItem;
use Espo\Modules\Sales\Entities\ReturnOrder;
use Espo\Modules\Sales\Entities\SalesOrder;
use Espo\Core\Currency\ConfigDataProvider as CurrencyConfigDataProvider;
use Espo\Modules\Sales\Entities\SupplierBill;
use Espo\Modules\Sales\Entities\SupplierCredit;
use Espo\Modules\Sales\Entities\TaxCode;
use Espo\Modules\Sales\Entities\TaxLineItem;
use Espo\Modules\Sales\Entities\TaxTotalItem;
use Espo\Modules\Sales\Tools\Currency\CurrencyRateProvider;
use Espo\Modules\Sales\Tools\PaymentTerms\InstallmentLine;
use Espo\Modules\Sales\Tools\Price\DefaultPriceBookProvider;
use Espo\Modules\Sales\Tools\Sales\ConfigDataProvider;
use Espo\Modules\Sales\Tools\Sales\HavingCurrencyRateEntity;
use Espo\Modules\Sales\Tools\Sales\HavingDiscountOrder;
use Espo\Modules\Sales\Tools\Sales\HavingTaxInclusiveOrder;
use Espo\Modules\Sales\Tools\Sales\IssuableOrder;
use Espo\Modules\Sales\Tools\Sales\IssuanceLockingHelper;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\Modules\Sales\Tools\Sales\OrderEntityUtil;
use Espo\Modules\Sales\Tools\Sales\OrderItem;
use Espo\Modules\Sales\Tools\Tax\CalculationItem;
use Espo\Modules\Sales\Tools\Tax\PriceInclusiveHelper;
use Espo\Modules\Sales\Tools\Tax\RoundingHavingOrder;
use Espo\Modules\Sales\Tools\Tax\TaxableOrder;
use Espo\Modules\Sales\Tools\Tax\TaxCalculationResult;
use Espo\Modules\Sales\Tools\Tax\TaxCalculator;
use Espo\Modules\Sales\Tools\Tax\TaxLineSaveItem;
use Espo\Modules\Sales\Tools\Tax\TaxRoundingLevel;
use Espo\Modules\Sales\Tools\Tax\TaxTotalLine;
use Espo\ORM\EntityManager;
use LogicException;
use stdClass;

class BeforeSaveProcessor
{
    private const ROUND_INTERMEDIATE_PRECISION = 4;

    private const ATTR_SHIPPING_COST = 'shippingCost';
    private const ATTR_SHIPPING_COST_CURRENCY = 'shippingCostCurrency';
    private const ATTR_AMOUNT = OrderEntity::FIELD_AMOUNT;
    private const ATTR_ITEM_LIST = OrderEntity::ATTR_ITEM_LIST;
    private const ATTR_HAS_INVENTORY_ITEMS = 'hasInventoryItems';
    private const ATTR_ACCOUNT_ID = 'accountId';
    private const ATTR_ACCOUNT_NAME = 'accountName';

    public function __construct(
        private EntityManager $entityManager,
        private Metadata $metadata,
        private CurrencyConfigDataProvider $currencyConfig,
        private ConfigDataProvider $configDataProvider,
        private TaxCalculator $taxCalculator,
        private ShippingSaveProcessor $shippingSaveProcessor,
        private ProcessorHelper $helper,
        private RoundingUtil $roundingUtil,
        private DefaultPriceBookProvider $defaultPriceBookProvider,
        private CurrencyConverterUtil $currencyConverterUtil,
        private CurrencyRateProvider $currencyRateProvider,
        private IssuanceLockingHelper $issuanceLockingHelper,
    ) {}

    public function process(OrderEntity $order): void
    {
        $this->setTax($order);
        $this->setIsTaxInclusive($order);
        $this->setItems($order);
        $this->calculatePaymentTerms($order);
        $this->setAccount($order);
    }

    /**
     * Processed after the next-number hook is processed.
     */
    public function processLate(OrderEntity $order): void
    {
        $this->setNumber($order);
        $this->setName($order);
    }

    private function setItems(OrderEntity $order): void
    {
        if ($order->isNew() && !$order->has(self::ATTR_ITEM_LIST)) {
            $order->set(self::ATTR_ITEM_LIST, []);
        }

        $this->correctItemList($order);

        if (
            $order->has(self::ATTR_ITEM_LIST) &&
            $order->isAttributeWritten(self::ATTR_ITEM_LIST)
        ) {
            $this->calculateItems($order);

            if ($order->hasAttribute(self::ATTR_HAS_INVENTORY_ITEMS)) {
                $order->set(self::ATTR_HAS_INVENTORY_ITEMS, $order->getInventoryProductIds() !== []);
            }

            return;
        }

        // Implies that the item list is not set.
        if (
            !$order->isNew() &&
            $order->isAttributeChanged(self::ATTR_AMOUNT)
        ) {
            $order->set(self::ATTR_AMOUNT, $order->getFetched(self::ATTR_AMOUNT));
        }

        if ($order->isChangedToRecalculateItems()) {
            $order->loadItemListField();

            $this->calculateItems($order);
        }
    }

    public function calculateItems(OrderEntity $order): void
    {
        $this->setDefaultCurrencyIfNotSet($order);
        $this->setLocalCurrency($order);
        $this->syncItemCurrency($order);
        $this->startTaxSaveProcess($order);
        $this->calculateItemAmounts($order);
        $this->processItems($order);
        $this->calculateTotalAmounts($order);
        $this->sanitizeItems($order);
        $this->syncCurrency($order);
        $this->calculateShipping($order);
        $this->prepareTaxTotals($order);
        $this->calculateTaxTotal($order);
        $this->calculateGrantTotalAmount($order);
        $this->calculateLocal($order);
        $this->setDiscountValues($order);
    }

    private function setAccount(OrderEntity $quote): void
    {
        if (
            !$quote instanceof Quote &&
            !$quote instanceof SalesOrder &&
            !$quote instanceof Invoice
        ) {
            return;
        }

        if ($quote->get(self::ATTR_ACCOUNT_ID)) {
            return;
        }

        $opportunity = $quote->getOpportunity();

        if (!$opportunity) {
            return;
        }

        $accountId = $opportunity->getAccount()?->getId();

        if (!$accountId) {
            return;
        }

        $quote->set(self::ATTR_ACCOUNT_ID, $accountId);
    }

    private function setNumber(OrderEntity $order): void
    {
        if (!$this->metadata->get("entityDefs.{$order->getEntityType()}.fields.number.useAutoincrement")) {
            return;
        }

        $field = OrderEntity::FIELD_NUMBER_A;

        if (
            $order instanceof Invoice &&
            $order->getType() === Invoice::TYPE_DEBIT_NOTE &&
            !$this->configDataProvider->isDebitNoteNumberingDisabled()
        ) {
            $field = Invoice::FIELD_NUMBER_DEBIT_NOTE_A;
        }

        if ($order instanceof IssuableOrder && $order->getFetched(OrderEntity::FIELD_WAS_ISSUED)) {
            return;
        }

        if (
            $order instanceof IssuableOrder &&
            !$order->isIssued() &&
            $this->configDataProvider->isDraftNumberingEnabled()
        ) {
            $field = OrderEntity::FIELD_NUMBER_DRAFT_A;
        }

        if (!$order->isAttributeWritten($field)) {
            return;
        }

        $order->setNumber($order->get($field));
    }

    private function setName(OrderEntity $order): void
    {
        if (!$this->metadata->get("entityDefs.{$order->getEntityType()}.fields.name.syncWithNumber")) {
            return;
        }

        $order->setName($order->getNumber());
    }

    private function calculateItem(
        OrderItem &$item,
        OrderEntity $order,
        Currency &$discountAmount,
        Currency &$taxAmount,
        int $index,
    ): void {

        $quantity = $item->getQuantity();
        $productId = $item->getProductId();
        /** @var float|numeric-string|null $unitPrice */
        $unitPrice = $item->get(QuoteItem::FIELD_UNIT_PRICE);
        $unitCurrency = $item->get(QuoteItem::FIELD_UNIT_PRICE . 'Currency');
        $unitWeight = $item->get(QuoteItem::FIELD_UNIT_WEIGHT);
        $taxCodeId = $item->get(QuoteItem::ATTR_TAX_CODE_ID);

        $this->prePrepareItem($item/*, $order*/);

        /** @var float|string|null $taxRate */
        $taxRate = $item->get(QuoteItem::FIELD_TAX_RATE);

        $product = $productId ?
            $this->entityManager->getRDBRepositoryByClass(Product::class)->getById($productId) : null;

        if (
            $product &&
            $unitWeight === null
        ) {
            $this->setItemAttribute($item, QuoteItem::FIELD_UNIT_WEIGHT, $product->getWeight());

            $unitWeight = $item->get(QuoteItem::FIELD_UNIT_WEIGHT);
        }

        if (
            ($order instanceof Invoice || $order instanceof CreditNote) &&
            $product &&
            !$product->isSubscribable()
        ) {
            $this->setItemAttributes($item, [
                InvoiceItem::FIELD_PERIOD_START_DATE => null,
                InvoiceItem::FIELD_PERIOD_END_DATE => null,
            ]);
        }

        $this->setItemAttributes($item, [
            QuoteItem::FIELD_WEIGHT => $this->calculateItemWeight($unitWeight, $quantity),
            QuoteItem::FIELD_DISCOUNT => 0.0,
            self::ATTR_ACCOUNT_ID => $order->getAccount()?->getId(),
            self::ATTR_ACCOUNT_NAME => $order->getAccount()?->getName(),
        ]);

        $this->setItemAttributeIfNull($item, QuoteItem::FIELD_LIST_PRICE, $unitPrice);
        $this->setItemAttributeIfNull($item, QuoteItem::FIELD_LIST_PRICE . 'Currency', $unitCurrency);

        if ($this->noListPrice($order)) {
            $this->setItemAttribute($item, QuoteItem::FIELD_LIST_PRICE, $unitPrice);
            $this->setItemAttribute($item, QuoteItem::FIELD_LIST_PRICE . 'Currency', $unitCurrency);
        }

        if ($unitPrice !== null && $quantity !== null) {
            $unit = (string) $unitPrice;
            /** @var numeric-string $list */
            $list = (string) ($item->get(QuoteItem::FIELD_LIST_PRICE) ?? '0');

            $qty = (string) $quantity;

            if (!ProcessorHelper::isZero($list)) {
                $itemDiscount = Calc::multiply(
                    Calc::divide(
                        Calc::subtract($list, $unit),
                        $list
                    ),
                    '100'
                );

                $itemDiscount = $this->roundAmount($itemDiscount, $order);

                $this->setItemAttribute($item, QuoteItem::FIELD_DISCOUNT, (float) $itemDiscount);
            }

            $itemDiscountAmount = Calc::multiply(
                Calc::subtract($list, $unit),
                $qty
            );

            $itemDiscountAmount = $this->roundAmount($itemDiscountAmount, $order);

            $discountAmount = $discountAmount->add(Currency::create($itemDiscountAmount, $this->getCurrency($order)));
        }

        if (!isset($unitPrice) || !isset($quantity)) {
            return;
        }

        if (
            $this->configDataProvider->isTaxCodesEnabled() &&
            OrderEntityUtil::isWithTax($order->getEntityType()) &&
            $taxCodeId
        ) {
            $taxCode = $this->helper->getTaxCode($taxCodeId);

            /** @var numeric-string $unit */
            $unit = (string) $unitPrice;
            $qty = (string) $quantity;

            $unitPriceValue = new Currency($unit, $this->getCurrency($order));

            $itemTaxCalculation = $this->calculateItemTax(
                unit: $unitPriceValue,
                quantity: $qty,
                taxCode: $taxCode,
                order: $order,
                product: $product,
                index: $index,
            );

            $this->updateItemAfterTax($order, $itemTaxCalculation, $item);

            $taxAmount = $taxAmount->add($itemTaxCalculation->taxAmount);

            return;
        }

        if ($this->configDataProvider->isTaxCodesEnabled()) {
            return;
        }

        $this->calculateItemTaxNoTaxCodes(
            taxRate: $taxRate,
            unitPrice: $unitPrice,
            quantity: $quantity,
            order: $order,
            item: $item,
            taxAmount: $taxAmount,
        );

    }

    private function sanitizeItem(stdClass $item, string $entityType): stdClass
    {
        $itemEntityType = OrderEntityUtil::getItemEntityType($entityType);

        $entity = $this->entityManager->getNewEntity($itemEntityType);

        $entity->set($item);

        return $entity->getValueMap();
    }

    private function setTax(OrderEntity $quote): void
    {
        if (!$quote instanceof TaxableOrder) {
            return;
        }

        if (!$quote->isAttributeChanged(OrderEntity::ATTR_TAX_ID)) {
            return;
        }

        $tax = $quote->getTax();

        $rate = $tax?->getRate() ?? null;
        $shippingTaxMode = $tax?->getShippingMode() ?? null;

        $quote->setTaxRate($rate);
        $quote->setShippingTaxMode($shippingTaxMode);
    }

    private function calculateShipping(OrderEntity $order): void
    {
        if (
            !$order instanceof Quote &&
            !$order instanceof SalesOrder &&
            !$order instanceof Invoice &&
            !$order instanceof ReturnOrder &&
            !$order instanceof CreditNote &&
            !$order instanceof PurchaseOrder &&
            !$order instanceof SupplierBill &&
            !$order instanceof SupplierCredit
        ) {
            return;
        }

        $shippingTaxString = $this->shippingSaveProcessor->process($order);

        $shippingTaxAmount = Currency::create($shippingTaxString, $this->getCurrency($order));
        $taxAmount = $order->getTaxAmount() ?? $this->createZero($order);

        $taxAmount = $taxAmount->add($shippingTaxAmount);

        $order->setTaxAmount($taxAmount);
    }

    private function correctItemList(OrderEntity $quote): void
    {
        if (!$quote->has(self::ATTR_ITEM_LIST)) {
            return;
        }

        /** @var stdClass[] $itemList */
        $itemList = $quote->get(self::ATTR_ITEM_LIST) ?? [];

        foreach ($itemList as $i => $o) {
            if (!is_array($o)) {
                continue;
            }

            $itemList[$i] = (object) $o;

            $quote->set(self::ATTR_ITEM_LIST, $itemList);
        }
    }

    /**
     * @return stdClass[]
     */
    private static function getItemListFromOrder(OrderEntity $order): array
    {
        /** @var stdClass[]  */
        return $order->get(self::ATTR_ITEM_LIST) ?? [];
    }


    private function calculateTotalAmounts(OrderEntity $order): void
    {
        $amount = '0.0';
        $weight = 0.0;

        foreach ($order->getItems() as $item) {
            /** @var numeric-string $itemAmount */
            $itemAmount = (string) ($item->get(QuoteItem::FIELD_AMOUNT) ?? '0');
            $itemWeight = $item->get(QuoteItem::FIELD_WEIGHT);

            $amount = Calc::add($amount, $itemAmount);

            $weight += $itemWeight ?? 0;
        }

        $weight = round($weight, self::ROUND_INTERMEDIATE_PRECISION);

        $order->set(OrderEntity::FIELD_WEIGHT, $weight);

        $order->setAmount(Currency::create($amount, $this->getCurrency($order)));
    }

    private function processItems(OrderEntity $order): void
    {
        $taxAmount = $this->createZero($order);
        $discountAmount = $this->createZero($order);
        $roundingAmount = $this->createZero($order);

        $items = $order->getItems();

        foreach ($items as $i => $item) {
            $this->calculateItem(
                item: $item,
                order: $order,
                discountAmount: $discountAmount,
                taxAmount: $taxAmount,
                index: $i,
            );

            $items[$i] = $item;
        }

        $order->setItems($items);

        $discountAmount = $this->roundingUtil->round($discountAmount);
        $roundingAmount = $this->roundingUtil->round($roundingAmount);

        if ($order instanceof RoundingHavingOrder) {
            $order->setRoundingAmount($roundingAmount);
        }

        if ($order instanceof HavingDiscountOrder) {
            $order->setDiscountAmount($discountAmount);
        }

        if ($order instanceof TaxableOrder) {
            $order->setTaxAmount($taxAmount);
        }
    }

    private function calculateGrantTotalAmount(OrderEntity $order): void
    {
        $zero = $this->createZero($order);

        $amount = $order->getAmount() ?? $zero;
        $shippingAmount = $order->getShippingCost() ?? $zero;
        $taxAmount = $this->createZero($order);

        if ($order instanceof TaxableOrder) {
            $shippingAmount = $order->getShippingAmount() ?? $zero;
            $taxAmount = $order->getTaxAmount() ?? $zero;
        }

        $grandTotal = $amount
            ->add($shippingAmount)
            ->add($taxAmount);

        if ($order instanceof RoundingHavingOrder && $order->getRoundingAmount()) {
            $grandTotal = $grandTotal->add($order->getRoundingAmount());
        }

        $grandTotal = $this->roundingUtil->round($grandTotal);

        $grandTotal = $this->processTotalRounding($order, $grandTotal);

        $order->setGrandTotalAmount($grandTotal);
    }

    private function calculateItemAmounts(OrderEntity $order): void
    {
        $items = $order->getItems();

        foreach ($items as $i => $item) {
            if (
                $item->get(QuoteItem::FIELD_UNIT_PRICE) === null ||
                $item->getQuantity() === null
            ) {
                $this->setItemAttribute($item, QuoteItem::FIELD_AMOUNT, null);

                if ($order instanceof HavingCurrencyRateEntity) {
                    $this->setItemAttribute($item, QuoteItem::FIELD_AMOUNT_LOCAL, null);
                }

                $items[$i] = $item;

                continue;
            }

            /** @var numeric-string $unit */
            $unit = (string) $item->get(QuoteItem::FIELD_UNIT_PRICE);
            /** @var numeric-string $quantity */
            $quantity = (string) $item->getQuantity();

            $value = Calc::multiply($unit, $quantity);
            $value = $this->roundAmount($value, $order);

            $this->setItemAttribute($item, QuoteItem::FIELD_AMOUNT, (float) $value);

            if ($order instanceof HavingCurrencyRateEntity) {
                $amount = Currency::create($value, $this->getCurrency($order));

                /** @noinspection PhpParamsInspection */
                $amountLocal = $this->currencyConverterUtil->convertToLocal($amount, $order);

                $this->setItemAttribute($item, QuoteItem::FIELD_AMOUNT_LOCAL, $amountLocal->getAmountAsString());
            }

            $items[$i] = $item;
        }

        $order->setItems($items);
    }

    private function setDefaultCurrencyIfNotSet(OrderEntity $order): void
    {
        if (
            !$order->hasAttribute(OrderEntity::ATTR_AMOUNT_CURRENCY) ||
            $order->has(OrderEntity::ATTR_AMOUNT_CURRENCY)
        ) {
            return;
        }

        $order->set(OrderEntity::ATTR_AMOUNT_CURRENCY, $this->currencyConfig->getDefaultCurrency());
    }

    private function noListPrice(OrderEntity $order): bool
    {
        if (
            ($order instanceof PurchaseOrder) &&
            !$this->configDataProvider->isPurchaseOrderListPriceEnabled()
        ) {
            return true;
        }

        if (
            (
                $order instanceof Invoice ||
                $order instanceof CreditNote
            ) &&
            !$this->configDataProvider->isInvoiceListPriceEnabled()
        ) {
            return true;
        }

        return false;
    }

    private function calculateItemWeight(?float $unitWeight, int|float|null $quantity): ?float
    {
        return $unitWeight !== null && $quantity !== null ?
            round($unitWeight * $quantity, self::ROUND_INTERMEDIATE_PRECISION) :
            null;
    }

    private function getCurrency(OrderEntity $order): string
    {
        return $order->getAmountCurrency() ?? $this->currencyConfig->getDefaultCurrency();
    }

    private function updateItemAfterTax(OrderEntity $order, TaxCalculationResult $calculation, OrderItem &$item): void
    {
        if (
            $order instanceof Invoice ||
            $order instanceof CreditNote ||
            $order instanceof SupplierBill ||
            $order instanceof SupplierCredit
        ) {
            $this->setItemAttributes($item, [
                InvoiceItem::FIELD_UNIT_PRICE_NET => $calculation->unitNet->getAmountAsString(),
                InvoiceItem::FIELD_TAX_AMOUNT => $calculation->taxAmount->getAmountAsString(),
            ]);
        }

        if ($calculation->lineAmount === null) {
            return;
        }

        $this->setItemAttributes($item, [
            QuoteItem::FIELD_AMOUNT => $calculation->lineAmount->getAmountAsString(),
        ]);

        if ($order instanceof HavingCurrencyRateEntity) {
            /** @noinspection PhpParamsInspection */
            $amountLocal = $this->currencyConverterUtil->convertToLocal($calculation->lineAmount, $order);

            $this->setItemAttributes($item, [
                QuoteItem::FIELD_AMOUNT_LOCAL => $amountLocal->getAmountAsString()
            ]);
        }
    }

    /**
     * @param numeric-string $quantity
     */
    private function calculateItemTax(
        Currency $unit,
        string $quantity,
        TaxCode $taxCode,
        OrderEntity $order,
        ?Product $product,
        int $index,
    ): TaxCalculationResult {

        $calculation = $this->taxCalculator->prepare(
            unit: $unit,
            quantity: $quantity,
            taxCode: $taxCode,
            order: $order,
        );

        foreach ($calculation->items as $calculatedItem) {
            $this->processItemTax(
                calculatedItem: $calculatedItem,
                product: $product,
                order: $order,
                index: $index,
            );
        }

        return $calculation;
    }

    private function processItemTax(
        CalculationItem $calculatedItem,
        ?Product $product,
        OrderEntity $order,
        int $index,
    ): void {

        $taxLineItem = $this->entityManager->getRDBRepositoryByClass(TaxLineItem::class)->getNew();

        $taxLineItem
            ->setComponent(TaxLineItem::COMPONENT_ITEM)
            ->setTaxCode($calculatedItem->taxCode)
            ->setRate($calculatedItem->rate)
            ->setProduct($product);

        $this->helper->setTaxLineItemAmounts($taxLineItem, $calculatedItem, $order);

        $saveItem = new TaxLineSaveItem(
            taxLineItem: $taxLineItem,
            index: $index,
            isInPrice: $calculatedItem->isInPrice,
        );

        if ($order instanceof TaxableOrder) {
            $order->addTaxLineSaveItem($saveItem);
        }
    }

    private function prePrepareItem(OrderItem &$item): void
    {
        if ($item->get(QuoteItem::FIELD_UNIT_WEIGHT) === null) {
            $this->setItemAttribute($item, QuoteItem::FIELD_UNIT_WEIGHT, null);
        }

        if ($this->configDataProvider->isTaxCodesEnabled()) {
            $this->setItemAttribute($item, QuoteItem::FIELD_TAX_RATE, null);
        } else {
            $this->setItemAttribute($item, QuoteItem::ATTR_TAX_CODE_ID, null);
        }
    }

    /**
     * @param numeric-string $itemTaxAmount
     */
    private function setItemTaxAmount(OrderEntity $order, string $itemTaxAmount, OrderItem &$item): void
    {
        if (
            $order instanceof Invoice ||
            $order instanceof CreditNote ||
            $order instanceof SupplierBill ||
            $order instanceof SupplierCredit
        ) {
            $this->setItemAttributes($item, [
                'taxAmount' => $itemTaxAmount,
            ]);
        }
    }

    private function prepareTaxTotals(OrderEntity $order): void
    {
        if (
            !$this->configDataProvider->isTaxCodesEnabled() ||
            !$order instanceof TaxableOrder ||
            $order->getTaxLineSaveItems() === null
        ) {
            return;
        }

        $totalLines = $this->prepareTaxTotalLines($order);

        foreach ($totalLines as $totalLine) {
            $taxTotalItem = $this->entityManager->getRDBRepositoryByClass(TaxTotalItem::class)->getNew();

            $taxTotalItem
                ->setTaxCode($totalLine->taxCode)
                ->setAmount($totalLine->amount)
                ->setBaseAmount($totalLine->baseAmount->getAmountAsString())
                ->setAmountLocal($totalLine->amountLocal)
                ->setBaseAmountLocal($totalLine->baseAmountLocal?->getAmountAsString() ?? null);

            $order->addTaxTotalSaveItem($taxTotalItem);
        }
    }

    private function calculateTaxTotal(OrderEntity $order): void
    {
        if (
            !$this->configDataProvider->isTaxCodesEnabled() ||
            !$order instanceof TaxableOrder
        ) {
            return;
        }

        $taxAmount = $this->createZero($order);

        foreach ($order->getTaxTotalSaveItems() as $item) {
            $taxAmount = $taxAmount->add($item->getAmount());
        }

        $order->setTaxAmount($taxAmount);
    }

    private function syncCurrency(OrderEntity $order): void
    {
        if ($order->hasAmountCurrency()) {
            $order->syncCurrency();

            if ($order->get(self::ATTR_SHIPPING_COST) === null) {
                $order->setMultiple([self::ATTR_SHIPPING_COST_CURRENCY => null]);
            }
        }
    }

    private function createZero(OrderEntity $order): Currency
    {
        return new Currency('0', $this->getCurrency($order));
    }

    /**
     * @param numeric-string $value
     * @return numeric-string
     */
    private function roundAmount(string $value, OrderEntity $order): string
    {
        return $this->roundingUtil->roundAmount($value, $this->getCurrency($order));
    }

    /**
     * @return TaxTotalLine[]
     */
    private function prepareTaxTotalLines(TaxableOrder $order): array
    {
        if (!$order instanceof OrderEntity) {
            throw new LogicException();
        }

        /** @var array<string, TaxTotalLine> $map */
        $map = [];

        /** @var TaxCode[] $taxCodes */
        $taxCodes = [];

        foreach ($order->getTaxLineSaveItems() as $saveItem) {
            $taxCode = $saveItem->taxLineItem->getTaxCode();

            $this->processTaxTotalIteration($saveItem, $taxCode, $map);

            foreach ($taxCodes as $it) {
                if ($taxCode->getId() === $it->getId()) {
                    continue 2;
                }
            }

            $taxCodes[] = $taxCode;
        }

        usort($taxCodes, fn (TaxCode $a, TaxCode $b) => $a->getOrder() - $b->getOrder());

        /** @var TaxTotalLine[] $totalLines */
        $totalLines = [];

        foreach ($taxCodes as $taxCode) {
            $totalLines[] = $map[$taxCode->getId()] ?? throw new LogicException();
        }

        $totalLines = $this->roundTaxTotalLines($order, $totalLines);

        if ($order instanceof RoundingHavingOrder && $order->getRoundingProfile()) {
            return $totalLines;
        }

        [$hasRoundingTotalInclusive, $hasRoundingCoarserThanPrecision] = $this->analyzeTotalLines($totalLines, $order);

        if ($hasRoundingTotalInclusive) {
            /** @noinspection PhpParamsInspection */
            $diff = $this->calculateTotalRoundingInclusiveDiff($order, $totalLines);

            if ($hasRoundingCoarserThanPrecision) {
                /** @noinspection PhpParamsInspection */
                //$this->addTotalDiffToAmount($order, $diff);
                $this->addTotalDiffToRoundingAmount($order, $diff);
            } else {
                /** @noinspection PhpParamsInspection */
                $this->addTotalDiffToTax($order, $diff, $totalLines);
            }
        }

        return $totalLines;
    }

    /**
     * @param array<string, TaxTotalLine> $map
     */
    private function processTaxTotalIteration(TaxLineSaveItem $saveItem, TaxCode $taxCode, array &$map): void
    {
        $item = $saveItem->taxLineItem;

        $codeId = $taxCode->getId();

        $itAmount = $item->getAmount();
        $itAmountLocal = $item->getAmountLocal();
        $itBaseAmount = $item->getBaseAmount();
        $itBaseAmountLocal = $item->getBaseAmountLocal();

        if ($taxCode->getRoundingLevel() === TaxRoundingLevel::Total) {
            $itAmount = $item->getAmountPrecise();
            $itAmountLocal = $item->getAmountLocalPrecise();
            $itBaseAmountLocal = $item->getBaseAmountLocalPrecise();
        }

        $itemTotalLine = new TaxTotalLine(
            taxCode: $taxCode,
            amount: $itAmount,
            baseAmount: $itBaseAmount,
            amountLocal: $itAmountLocal,
            baseAmountLocal: $itBaseAmountLocal,
            isInPrice: $saveItem->isInPrice,
        );

        $totalLine = $map[$codeId] ?? null;

        if ($totalLine) {
            $amountLocal = $totalLine->amountLocal && $itAmountLocal ?
                $itAmountLocal->add($totalLine->amountLocal) : null;

            $baseAmountLocal = $totalLine->baseAmountLocal && $itBaseAmountLocal ?
                $itBaseAmountLocal->add($totalLine->baseAmountLocal) : null;

            $itemTotalLine = new TaxTotalLine(
                taxCode: $taxCode,
                amount: $itAmount->add($totalLine->amount),
                baseAmount: $itBaseAmount->add($totalLine->baseAmount),
                amountLocal: $amountLocal,
                baseAmountLocal: $baseAmountLocal,
                isInPrice: $saveItem->isInPrice,
            );
        }

        $map[$codeId] = $itemTotalLine;
    }

    /**
     * @param TaxTotalLine[] $totalLines
     * @return TaxTotalLine[]
     */
    private function roundTaxTotalLines(OrderEntity $order, array $totalLines): array
    {
        foreach ($totalLines as $i => $totalLine) {
            if ($totalLine->taxCode->getRoundingLevel() !== TaxRoundingLevel::Total) {
                continue;
            }

            $factor = $totalLine->taxCode->getRoundingFactor();

            if ($totalLine->amountLocal && $order instanceof HavingCurrencyRateEntity) {
                $amountLocal = $this->roundingUtil->round($totalLine->amountLocal, $factor);
                $totalLine = $totalLine->withAmountLocal($amountLocal);

                /** @noinspection PhpParamsInspection */
                $amount = $this->currencyConverterUtil->convertFromLocal($amountLocal, $order);

                if ($totalLine->baseAmountLocal) {
                    // We round by the currency precision, not by the factor.
                    $baseAmountLocal = $this->roundingUtil->round($totalLine->baseAmountLocal);

                    $totalLine = $totalLine->withBaseAmountLocal($baseAmountLocal);
                }
            } else {
                $amount = $this->roundingUtil->round($totalLine->amount, $factor);
            }

            $totalLine = $totalLine->withAmount($amount);

            $totalLines[$i] = $totalLine;
        }

        return $totalLines;
    }

    private function processTotalRounding(OrderEntity $order, Currency $amount): Currency
    {
        if (!$order instanceof Invoice && !$order instanceof CreditNote) {
            return $amount;
        }

        $profile = $order->getRoundingProfile();

        if (!$profile) {
            return $amount;
        }

        $roundedAmount = $this->roundingUtil->round($amount, $profile->getRoundingFactor());

        $diff = $roundedAmount->subtract($amount);
        $rounding = $order->getRoundingAmount() ?? $this->createZero($order);
        $rounding = $rounding->add($diff);

        $order->setRoundingAmount($rounding);

        return $roundedAmount;
    }

    private function syncItemCurrency(OrderEntity $order): void
    {
        if (!$order->hasAmountCurrency()) {
            return;
        }

        $items = $order->getItems();

        $code = $order->getAmountCurrency();

        foreach ($items as $i => $item) {
            $item = $item
                ->with(QuoteItem::FIELD_LIST_PRICE . 'Currency', $code)
                ->with(QuoteItem::FIELD_UNIT_PRICE . 'Currency', $code)
                ->with(QuoteItem::FIELD_AMOUNT . 'Currency', $code);

            $items[$i] = $item;
        }

        $order->setItems($items);
    }

    private function startTaxSaveProcess(OrderEntity $order): void
    {
        if (!$order instanceof TaxableOrder) {
            return;
        }

        $order->clearTaxSaveItems(true);
    }

    private function setItemAttributeIfNull(OrderItem &$item, string $attribute, mixed $value): void
    {
        if ($item->get($attribute) !== null) {
            return;
        }

        $this->setItemAttribute($item, $attribute, $value);
    }

    private function setItemAttribute(OrderItem &$item, string $attribute, mixed $value): void
    {
        $item = $item->with($attribute, $value);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function setItemAttributes(OrderItem &$item, array $values): void
    {
        $item = $item->withData($values);
    }

    private function sanitizeItems(OrderEntity $order): void
    {
        $itemList = self::getItemListFromOrder($order);

        $itemList = array_map(
            fn ($item) => $this->sanitizeItem($item, $order->getEntityType()),
            $itemList
        );

        $order->set(self::ATTR_ITEM_LIST, $itemList);
    }

    private function setDiscountValues(OrderEntity $order): void
    {
        if (!$order instanceof HavingDiscountOrder) {
            return;
        }

        $amount = $order->getAmount() ?? $this->createZero($order);
        $discountAmount = $order->getDiscountAmount() ?? $this->createZero($order);

        $preDiscountedAmount = $amount->add($discountAmount);

        $order->setPreDiscountedAmount($preDiscountedAmount);
    }

    private function setIsTaxInclusive(OrderEntity $order): void
    {
        if (
            !$order instanceof Quote &&
            !$order instanceof SalesOrder &&
            !$order instanceof Invoice
        ) {
            return;
        }

        $priceBook = $order->getPriceBook();

        $isTaxInclusive = $priceBook ?
            $priceBook->isTaxInclusive() :
            $this->getDefaultPriceBook()?->isTaxInclusive() ?? false;

        $order->setIsTaxInclusive($isTaxInclusive);
    }

    private function getDefaultPriceBook(): ?PriceBook
    {
        return $this->defaultPriceBookProvider->get();
    }

    /**
     * @param TaxTotalLine[] $totalLines
     * @return array{bool, bool}
     */
    private function analyzeTotalLines(array $totalLines, OrderEntity $order): array
    {
        $hasRoundingTotalInclusive = false;
        $hasRoundingCoarserThanPrecision = false;

        foreach ($totalLines as $total) {
            if (
                !$total->isInPrice ||
                $total->taxCode->getRoundingLevel() !== TaxRoundingLevel::Total
            ) {
                continue;
            }

            $hasRoundingTotalInclusive = true;

            if (
                $total->taxCode->getRoundingFactor() !== null &&
                RoundingUtil::isFactorCourserThanPrecision(
                    $total->taxCode->getRoundingFactor(),
                    $this->roundingUtil->getPrecision($this->getCurrency($order))
                )
            ) {
                $hasRoundingCoarserThanPrecision = true;

                break;
            }
        }

        return [$hasRoundingTotalInclusive, $hasRoundingCoarserThanPrecision];
    }

    // Not used, otherwise debit won't match credit in reports.
    /*private function addTotalDiffToAmount(OrderEntity & TaxableOrder $order, Currency $diff): void
    {
        // Would need also to compensate the Amount Local. Converted and written further.

        $amount = $order->getAmount() ?? $this->createZero($order);

        $order->setAmount($amount->add($diff));
    }*/

    private function addTotalDiffToRoundingAmount(OrderEntity & TaxableOrder $order, Currency $diff): void
    {
        if (!$order instanceof RoundingHavingOrder) {
            return;
        }

        $order->setRoundingAmount($diff);
    }

    /**
     * @param TaxTotalLine[] $lines
     */
    private function addTotalDiffToTax(OrderEntity $order, Currency $diff, array &$lines): void
    {
        $copiedLines = $lines;

        $maxIndex = null;
        $maxAmount = null;

        foreach ($copiedLines as $i => $it) {
            if ($maxAmount === null) {
                $maxAmount = $it->amount;

                $maxIndex = $i;

                continue;
            }

            if ($it->amount->compare($maxAmount) > 0) {
                $maxAmount = $it->amount;
                $maxIndex = $i;
            }
        }

        if ($maxIndex === null) {
            return;
        }

        $item = $lines[$maxIndex];

        $item = $item->withAmount($item->amount->add($diff));

        if ($item->amountLocal && $order instanceof HavingCurrencyRateEntity) {
            /** @noinspection PhpParamsInspection */
            $diffLocal = $this->currencyConverterUtil->convertToLocal($diff, $order);

            $item = $item->withAmountLocal($item->amountLocal->add($diffLocal));
        }

        $lines[$maxIndex] = $item;
    }

    /**
     * @param TaxTotalLine[] $lines
     */
    private function calculateTotalRoundingInclusiveDiff(
        OrderEntity & TaxableOrder $order,
        array $lines,
    ): Currency {

        $expected = Currency::create('0', $this->getCurrency($order));

        foreach ($order->getItems() as $item) {
            $qty = (string) ($item->getQuantity() ?? '0');
            /** @var numeric-string $unit */
            $unit = (string) ($item->get(QuoteItem::FIELD_UNIT_PRICE) ?? '0');

            $itAmount = Calc::multiply($qty, $unit);
            $itAmount = $this->roundAmount($itAmount, $order);

            $expected = $expected->add(Currency::create($itAmount, $expected->getCode()));
        }

        $shippingCost = $order->getShippingCost() ?? $this->createZero($order);
        $expected = $expected->add($shippingCost);

        $factual = $order->getAmount() ?? $this->createZero($order);

        $lines = array_filter($lines, fn ($it) => $it->isInPrice);
        $lines = array_values($lines);

        foreach ($lines as $line) {
            $factual = $factual->add($line->amount);
        }

        $shippingAmount = $order->getShippingAmount() ?? $this->createZero($order);

        $factual = $factual->add($shippingAmount);

        return $expected->subtract($factual);
    }

    private function setLocalCurrency(OrderEntity $order): void
    {
        if (!$order instanceof HavingCurrencyRateEntity) {
            return;
        }

        if (
            $order instanceof IssuableOrder &&
            $order->isIssued() &&
            (
                $this->issuanceLockingHelper->isEnabled() ||
                // To fix records created before v4.0.
                (
                    $order->getLocalCurrency() &&
                    $order->getCurrencyRate() !== null
                )
            )
        ) {
            return;
        }

        if (!$order->getLocalCurrency()) {
            $order->setLocalCurrency($this->currencyConfig->getBaseCurrency());
        }

        $localCode = $order->getLocalCurrency() ?? throw new LogicException("No local currency.");
        $code = $order->getAmountCurrency() ?? throw new LogicException("No currency.");

        if ($localCode === $order->getAmountCurrency()) {
            $order->setCurrencyRate('1');

            return;
        }

        if ($order->getCurrencyRate() === null) {
            $rate = $this->currencyRateProvider->get($code, $localCode);

            $order->setCurrencyRate($rate);
        }
    }

    private function calculateLocal(OrderEntity $order): void
    {
        if (
            !$order instanceof Invoice &&
            !$order instanceof CreditNote &&
            !$order instanceof SupplierBill &&
            !$order instanceof SupplierCredit
        ) {
            return;
        }

        //$amount = $order->getAmount() ?? throw new LogicException("No amount.");
        $shippingAmount = $order->getShippingAmount() ?? $this->createZero($order);

        $amountLocal = $this->calculateAmountLocal($order);

        //$amountLocal = $this->currencyConverterUtil->convertToLocal($amount, $order);
        $shippingAmountLocal = $this->currencyConverterUtil->convertToLocal($shippingAmount, $order);
        $taxAmountLocal = $this->calculateTaxAmountLocal($order, $amountLocal->getCode());

        $grandTotalLocal = $amountLocal
            ->add($shippingAmountLocal)
            ->add($taxAmountLocal);

        if ($order instanceof RoundingHavingOrder) {
            $roundingAmount = $order->getRoundingAmount() ?? $this->createZero($order);
            $roundingAmountLocal = $this->currencyConverterUtil->convertToLocal($roundingAmount, $order);

            $grandTotalLocal = $grandTotalLocal->add($roundingAmountLocal);
        }

        $order
            ->setAmountLocal($amountLocal)
            ->setShippingAmountLocal($shippingAmountLocal)
            ->setTaxAmountLocal($taxAmountLocal)
            ->setGrandTotalAmountLocal($grandTotalLocal);

        if ($order instanceof RoundingHavingOrder) {
            $order->setRoundingAmountLocal($roundingAmountLocal);
        }
    }

    private function calculateTaxAmountLocal(
        SupplierBill|SupplierCredit|CreditNote|Invoice $order,
        string $code,
    ): Currency {

        if (!$this->configDataProvider->isTaxCodesEnabled()) {
            $taxAmountLocal = $order->getTaxAmount() ?? $this->createZero($order);

            return $this->currencyConverterUtil->convertToLocal($taxAmountLocal, $order);
        }

        $taxAmountLocal = Currency::create('0', $code);

        foreach ($order->getTaxTotalSaveItems() as $item) {
            if ($item->getAmountLocal()) {
                $taxAmountLocal = $taxAmountLocal->add($item->getAmountLocal());
            }
        }

        return $taxAmountLocal;
    }

    public function calculatePaymentTerms(OrderEntity $order): void
    {
        if (
            !$order instanceof Invoice ||
            !$order->isNew() && !$order->isPaymentTermsToCalculate() ||
            $this->issuanceLockingHelper->isEnabled() && $order->isIssued()
        ) {
            return;
        }

        $order->clearInstallmentSaveItems(true);

        $paymentTermsProfile = $order->getPaymentTermsProfile();
        $issueDate = $order->getDateInvoiced();
        $localCode = $order->getLocalCurrency();
        $rate = $order->getCurrencyRate();

        if ($paymentTermsProfile) {
            // To be calculated further.
            $order->setDateDue(null);

            $order->setPaymentTermsNote($paymentTermsProfile->getNote());
        }

        if (!$localCode || $rate === null) {
            // Not supposed to happen.
            return;
        }

        $zero = $this->createZero($order);

        $total = $order->getGrandTotalAmount() ?? $zero;
        $totalLocal = $order->getGrandTotalAmountLocal() ?? Currency::create('0', $localCode);

        if (!$paymentTermsProfile && $order->getDateDue()) {
            $status = $this->computeInstallmentStatusOne($order);

            $order->addInstallmentSaveItem(
                new InstallmentLine(
                    date: $order->getDateDue(),
                    amount: $total,
                    amountLocal: $totalLocal,
                    percentage: '100.00',
                    status: $status,
                )
            );

            return;
        }

        if (!$paymentTermsProfile || !$issueDate) {
            return;
        }

        $items = $paymentTermsProfile->getItems();

        $sum = $this->createZero($order);
        $sumLocal = Currency::create('0', $localCode);

        $dateDue = null;

        $due = $order->getAmountDue() ?? $zero;
        $paid = $total->subtract($due);

        foreach ($items as $i => $item) {
            $itemDate = $issueDate->addDays($item->days);

            if ($i === count($items) - 1) {
                $amount = $total->subtract($sum);
                $amountLocal = $totalLocal->subtract($sumLocal);

                $dateDue = $itemDate;
            } else {
                $amount = $total->multiply($item->percentage)->divide('100.0');
                $amount = $this->roundingUtil->round($amount);

                $amountLocal = $this->currencyConverterUtil->convertToLocal($amount, $order);
            }

            if ($sum->add($amount)->compare($paid) <= 0) {
                $status = PaymentInstallment::STATUS_SETTLED;
            } else if ($sum->compare($paid) < 0) {
                $status = PaymentInstallment::STATUS_PARTIALLY_SETTLED;
            } else {
                $status = PaymentInstallment::STATUS_UNSETTLED;
            }

            $order->addInstallmentSaveItem(
                new InstallmentLine(
                    date: $issueDate->addDays($item->days),
                    amount: $amount,
                    amountLocal: $amountLocal,
                    percentage: $item->percentage,
                    status: $status,
                )
            );

            $sum = $sum->add($amount);
            $sumLocal = $sumLocal->add($amountLocal);
        }

        $order->setDateDue($dateDue);
    }

    private function computeInstallmentStatusOne(Invoice $order): string
    {
        $zero = $this->createZero($order);
        $total = $order->getGrandTotalAmount() ?? $zero;
        $amountDue = $order->getAmountDue() ?? $zero;

        if (
            $amountDue->compare($zero) > 0 &&
            $amountDue->compare($total) < 0
        ) {
            $status = PaymentInstallment::STATUS_PARTIALLY_SETTLED;
        } else if ($amountDue->compare($zero) <= 0) {
            $status = PaymentInstallment::STATUS_SETTLED;
        } else {
            $status = PaymentInstallment::STATUS_UNSETTLED;
        }

        return $status;
    }

    private function calculateAmountLocal(SupplierBill|SupplierCredit|CreditNote|Invoice $order): Currency
    {
        $localCurrency = $order->getLocalCurrency() ?? throw new LogicException("No local currency.");

        $amountLocal = Currency::create('0', $localCurrency);

        foreach ($order->getItems() as $item) {
            /** @var numeric-string $itemAmount */
            $itemAmount = $item->get(QuoteItem::FIELD_AMOUNT_LOCAL) ?? '0';

            $amountLocal = $amountLocal->add(
                Currency::create($itemAmount, $localCurrency)
            );
        }

        return $amountLocal;
    }

    private function calculateItemTaxNoTaxCodes(
        float|string|null $taxRate,
        float|string|null $unitPrice,
        float $quantity,
        OrderEntity $order,
        OrderItem &$item,
        Currency &$taxAmount,
    ): void {

        /** @var numeric-string $rate */
        $rate = (string)($taxRate ?? '0');

        if (ProcessorHelper::isZero($rate)) {
            return;
        }

        /** @var numeric-string $unit */
        $unit = (string) $unitPrice;
        $qty = (string) $quantity;

        $netAmount = $this->roundAmount(Calc::multiply($unit, $qty), $order);

        if ($order instanceof HavingTaxInclusiveOrder && $order->isTaxInclusive()) {
            $netAmount = PriceInclusiveHelper::obtainNetWithOneRate($netAmount, $rate);
            $netAmount = $this->roundAmount($netAmount, $order);

            $item = $item->with(QuoteItem::FIELD_AMOUNT, $netAmount);

            if ($order instanceof HavingCurrencyRateEntity) {
                /** @noinspection PhpParamsInspection */
                $amountLocal = $this->currencyConverterUtil->convertToLocal(
                    Currency::create($netAmount, $this->getCurrency($order)),
                    $order
                );

                $this->setItemAttribute($item, QuoteItem::FIELD_AMOUNT_LOCAL, $amountLocal->getAmountAsString());
            }
        }

        $itemTaxAmount = Calc::divide(Calc::multiply($netAmount, $rate), '100');

        $itemTaxAmount = $this->roundAmount($itemTaxAmount, $order);

        $this->setItemTaxAmount($order, $itemTaxAmount, $item);

        $taxAmount = $taxAmount->add(Currency::create($itemTaxAmount, $taxAmount->getCode()));
    }
}
