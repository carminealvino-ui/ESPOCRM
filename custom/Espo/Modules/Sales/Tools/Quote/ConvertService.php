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

use Espo\Core\Acl;
use Espo\Core\Acl\Table;
use Espo\Core\Currency\ConfigDataProvider as CurrencyConfig;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Field\Link;
use Espo\Core\Field\LinkParent;
use Espo\Core\FieldProcessing\Loader\Params as LoaderParams;
use Espo\Core\Utils\DateTime;
use Espo\Modules\Sales\Classes\FieldLoaders\Account\PaymentMethod as PaymentMethodLoader;
use Espo\Modules\Sales\Classes\FieldLoaders\Quote\InventoryDataLoader;
use Espo\Modules\Sales\Entities\CreditNote;
use Espo\Modules\Sales\Entities\InventoryNumber;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Entities\InvoiceItem;
use Espo\Modules\Sales\Entities\OpportunityItem;
use Espo\Modules\Sales\Entities\PaymentTermsProfile;
use Espo\Modules\Sales\Entities\SupplierBill;
use Espo\Modules\Sales\Entities\SupplierCredit;
use Espo\Modules\Sales\Tools\Currency\CurrencyRateProvider;
use Espo\Modules\Sales\Tools\Payment\Allocation;
use Espo\Modules\Sales\Tools\PaymentTermsProfile\TermsCalculator;
use Espo\Modules\Sales\Tools\Price\DefaultPriceBookProvider;
use Espo\Modules\Sales\Tools\Quote\Convert\Params;
use Espo\Modules\Sales\Tools\Sales\ConfigDataProvider;
use Espo\Modules\Sales\Tools\Sales\OrderEntityUtil;
use Espo\Modules\Sales\Tools\Tax\ProductRateService;
use Espo\Modules\Sales\Tools\Tax\TaxableOrder;
use Espo\Modules\Sales\Tools\TaxRule\RuleService;
use Espo\ORM\Collection;
use Espo\Core\Utils\Metadata;
use Espo\Modules\Crm\Entities\Account;
use Espo\Modules\Crm\Entities\Opportunity;
use Espo\Modules\Sales\Entities\DeliveryOrder;
use Espo\Modules\Sales\Entities\DeliveryOrderItem;
use Espo\Modules\Sales\Entities\Product;
use Espo\Modules\Sales\Entities\PurchaseOrder;
use Espo\Modules\Sales\Entities\Quote;
use Espo\Modules\Sales\Entities\QuoteItem;
use Espo\Modules\Sales\Entities\ReceiptOrder;
use Espo\Modules\Sales\Entities\ReturnOrder;
use Espo\Modules\Sales\Entities\ReturnOrderItem;
use Espo\Modules\Sales\Entities\SalesOrder;
use Espo\Modules\Sales\Entities\SalesOrderItem;
use Espo\Modules\Sales\Entities\Tax;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use LogicException;
use Traversable;

class ConvertService
{
    /** @var string[] */
    private array $ignoreItemAttributeList = [
        'id',
        'createdById',
        'createdByName',
        'modifiedById',
        'modifiedByName',
        'createdAt',
        'modifiedAt',
    ];

    public function __construct(
        private EntityManager $entityManager,
        private Acl $acl,
        private Metadata $metadata,
        private PaymentMethodLoader $paymentMethodLoader,
        private RuleService $taxRuleService,
        private ProductRateService $productRateService,
        private InventoryDataLoader $inventoryDataLoader,
        private ConfigDataProvider $configDataProvider,
        private CurrencyConfig $currencyConfig,
        private CurrencyRateProvider $currencyRateProvider,
        private TermsCalculator $termsCalculator,
        private DateTime $dateTime,
        private DefaultPriceBookProvider $defaultPriceBookProvider,
    ) {}

    /**
     * @throws Forbidden
     * @throws NotFound
     * @return array<string, mixed>
     */
    public function getAttributes(
        string $targetType,
        string $sourceType,
        string $sourceId,
        Params $params = new Params(),
    ): array {

        $source = $this->entityManager->getEntityById($sourceType, $sourceId);

        if (!$source) {
            throw new NotFound();
        }

        if (!$source instanceof OrderEntity && !$source instanceof Opportunity) {
            throw new LogicException("Bad source entity.");
        }

        $idAttribute = $this->getSourceIdAttribute($targetType, $sourceType);

        if (!$this->acl->check($source, Table::ACTION_READ)) {
            throw new Forbidden();
        }

        $tax = $this->getTax($source, $targetType);

        $itemList = $this->getItems($source, $targetType, $tax);

        /** @noinspection PhpInternalEntityUsedInspection */
        $source->loadLinkMultipleField('teams');

        $name = $source->get('name') === $source->get('number') ?
            null :
            $source->get('name');

        $attributes = [
            'name' => $name,
            'teamsIds' => $source->get('teamsIds'),
            'teamsNames' => $source->get('teamsNames'),
            $idAttribute => $sourceId,
            'amountCurrency' => $source->get('amountCurrency'),
            'shippingProviderId' => $source->get('shippingProviderId'),
            'shippingProviderName' => $source->get('shippingProviderName'),
            'shippingTaxMode' => $source->get('shippingTaxMode'),
            'priceBookId' => $source->get('priceBookId'),
            'priceBookName' => $source->get('priceBookName'),
        ];

        $toSetAmountsAndItems = true;

        if ($source instanceof Invoice && $targetType === Invoice::ENTITY_TYPE) {
            $toSetAmountsAndItems = false;
        }

        if ($toSetAmountsAndItems) {
            $attributes = [
                ...$attributes,
                OrderEntity::ATTR_ITEM_LIST => $itemList,
                OrderEntity::FIELD_AMOUNT => $source->get(OrderEntity::FIELD_AMOUNT),
                OrderEntity::FIELD_TAX_AMOUNT => $source->get(OrderEntity::FIELD_TAX_AMOUNT),
                'preDiscountedAmountCurrency' => $source->get('amountCurrency'),
                'taxAmountCurrency' => $source->get('amountCurrency'),
                'grandTotalAmountCurrency' => $source->get('amountCurrency'),
                'discountAmountCurrency' => $source->get('amountCurrency'),
                'shippingCost' => $source->get('shippingCost'),
                'shippingCostCurrency' => $source->get('amountCurrency'),
                'shippingAmount' => $source->get('shippingAmount'),
                'shippingAmountCurrency' => $source->get('shippingAmountCurrency'),
            ];
        }

        if ($source instanceof Invoice && $targetType === Invoice::ENTITY_TYPE) {
            unset($attributes['shippingCost']);
            unset($attributes['shippingAmount']);
            unset($attributes[OrderEntity::FIELD_TAX_AMOUNT]);
        }

        if ($source->hasAttribute(OrderEntity::FIELD_IS_TAX_INCLUSIVE)) {
            $attributes[OrderEntity::FIELD_IS_TAX_INCLUSIVE] = $source->get(OrderEntity::FIELD_IS_TAX_INCLUSIVE);
        }

        if ($tax) {
            $attributes = $this->applyTaxAttributes($attributes, $tax);
        }

        if (
            $sourceType === Quote::ENTITY_TYPE ||
            $sourceType === SalesOrder::ENTITY_TYPE ||
            $sourceType === Invoice::ENTITY_TYPE
        ) {
            $attributes['billingContactId'] = $source->get('billingContactId');
            $attributes['billingContactName'] = $source->get('billingContactName');
            $attributes['shippingContactId'] = $source->get('shippingContactId');
            $attributes['shippingContactName'] = $source->get('shippingContactName');
        }

        if ($source->hasAttribute('quoteId')) {
            $attributes['quoteId'] = $source->get('quoteId');
            $attributes['quoteName'] = $source->get('quoteName');
        }

        if ($source->hasAttribute('salesOrderId')) {
            $attributes['salesOrderId'] = $source->get('salesOrderId');
            $attributes['salesOrderName'] = $source->get('salesOrderName');
        }

        if ($source->hasAttribute('opportunityId')) {
            $attributes['opportunityId'] = $source->get('opportunityId');
            $attributes['opportunityName'] = $source->get('opportunityName');
        }

        if ($toSetAmountsAndItems) {
            $attributes = $this->fixAmounts($source, $itemList, $attributes);
        }

        $attributes['accountId'] = $source->get('accountId');
        $attributes['accountName'] = $source->get('accountName');

        if (
            $sourceType === PurchaseOrder::ENTITY_TYPE ||
            $sourceType === SupplierBill::ENTITY_TYPE
        ) {
            $attributes['supplierId'] = $source->get('supplierId');
            $attributes['supplierName'] = $source->get('supplierName');

            if (
                $targetType === SupplierCredit::ENTITY_TYPE ||
                $targetType === SupplierBill::ENTITY_TYPE
            ) {
                $attributes['supplierAddressStreet'] = $source->get('supplierAddressStreet');
                $attributes['supplierAddressCity'] = $source->get('supplierAddressCity');
                $attributes['supplierAddressState'] = $source->get('supplierAddressState');
                $attributes['supplierAddressCountry'] = $source->get('supplierAddressCountry');
                $attributes['supplierAddressPostalCode'] = $source->get('supplierAddressPostalCode');
            }
        }

        if (
            $sourceType === PurchaseOrder::ENTITY_TYPE ||
            $sourceType === ReturnOrder::ENTITY_TYPE
        ) {
            $attributes['shippingContactId'] = $source->get('shippingContactId');
            $attributes['shippingContactName'] = $source->get('shippingContactName');

            $attributes['warehouseId'] = $source->get('warehouseId');
            $attributes['warehouseName'] = $source->get('warehouseName');
        }

        $attributes['billingAddressStreet'] = $source->get('billingAddressStreet');
        $attributes['billingAddressCity'] = $source->get('billingAddressCity');
        $attributes['billingAddressState'] = $source->get('billingAddressState');
        $attributes['billingAddressCountry'] = $source->get('billingAddressCountry');
        $attributes['billingAddressPostalCode'] = $source->get('billingAddressPostalCode');

        if ($sourceType === SalesOrder::ENTITY_TYPE && $targetType === ReturnOrder::ENTITY_TYPE) {
            $attributes['fromAddressStreet'] = $source->get('shippingAddressStreet');
            $attributes['fromAddressCity'] = $source->get('shippingAddressCity');
            $attributes['fromAddressState'] = $source->get('shippingAddressState');
            $attributes['fromAddressCountry'] = $source->get('shippingAddressCountry');
            $attributes['fromAddressPostalCode'] = $source->get('shippingAddressPostalCode');
        } else {
            $attributes['shippingAddressStreet'] = $source->get('shippingAddressStreet');
            $attributes['shippingAddressCity'] = $source->get('shippingAddressCity');
            $attributes['shippingAddressState'] = $source->get('shippingAddressState');
            $attributes['shippingAddressCountry'] = $source->get('shippingAddressCountry');
            $attributes['shippingAddressPostalCode'] = $source->get('shippingAddressPostalCode');
        }

        if (
            $source instanceof Invoice && $targetType === CreditNote::ENTITY_TYPE ||
            $source instanceof Invoice && $targetType === Invoice::ENTITY_TYPE
        ) {
            $attributes['buyerReference'] = $source->getBuyerReference();
            $attributes['purchaseOrderReference'] = $source->getPurchaseOrderReference();

            $roundingProfile = $source->getRoundingProfile();

            if ($roundingProfile) {
                $attributes[OrderEntity::FIELD_ROUNDING_PROFILE . 'Id'] = $source->getRoundingProfile()->getId();
                $attributes[OrderEntity::FIELD_ROUNDING_PROFILE . 'Name'] = $source->getRoundingProfile()->getName();
            }
        }

        if (
            $source instanceof Invoice && $targetType === CreditNote::ENTITY_TYPE ||
            $source instanceof SupplierBill && $targetType === SupplierCredit::ENTITY_TYPE
        ) {
            if ($params->issue) {
                $attributes[OrderEntity::FIELD_STATUS] = CreditNote::STATUS_ISSUED;

                $attributes[CreditNote::ATTR_ALLOCATIONS] = CreditNote::serializeAllocations([
                    new Allocation(
                        target: LinkParent::createFromEntity($source),
                        amount: $source->getAmountDue(),
                    )
                ]);
            }

            $attributes[OrderEntity::FIELD_CURRENCY_RATE] = $source->getCurrencyRate();
            $attributes[OrderEntity::ATTR_AMOUNT_LOCAL_CURRENCY] =
                $source->get(OrderEntity::ATTR_AMOUNT_LOCAL_CURRENCY);
        }

        if ($targetType === Invoice::ENTITY_TYPE) {
            $code = $source->get(OrderEntity::ATTR_AMOUNT_CURRENCY) ?? $this->currencyConfig->getDefaultCurrency();

            $attributes[OrderEntity::FIELD_CURRENCY_RATE] = $this->currencyRateProvider->get($code);
        }

        if ($source instanceof Invoice && $targetType === Invoice::ENTITY_TYPE) {
            $attributes = [
                ...$attributes,
                'paymentMethodId' => $source->getPaymentMethod()?->getId(),
                'paymentMethodName' => $source->getPaymentMethod()?->getName(),
                'paymentMethodsIds' => $source->get('paymentMethodsIds'),
                'paymentMethodsNames' => $source->get('paymentMethodsNames'),
                'paymentMethodsColumns' => $source->get('paymentMethodsColumns'),
            ];
        }

        $this->loadInventoryData($targetType, $attributes);

        $this->filterAttributes($targetType, $attributes);

        $accountId = $source->get('accountId');

        if (!$accountId) {
            return $attributes;
        }

        $account = $this->entityManager->getRDBRepositoryByClass(Account::class)->getById($accountId);

        if (!$account) {
            return $attributes;
        }

        if ($targetType === Invoice::ENTITY_TYPE) {
            $paymentTermsProfileId = $account->get(OrderEntity::FIELD_PAYMENT_TERMS_PROFILE . 'Id');

            if ($paymentTermsProfileId) {
                $attributes = $this->addPaymentTermsProfile($paymentTermsProfileId, $attributes);
            }
        }

        if ($sourceType === SalesOrder::ENTITY_TYPE) {
            $attributes = $this->addAccountPaymentMethods($account, $attributes);
        }

        if ($sourceType === Opportunity::ENTITY_TYPE) {
            $attributes = $this->addAccountPaymentMethods($account, $attributes);

            if (!$source->get('priceBookId')) {
                $attributes['priceBookId'] = $account->get('priceBookId');
                $attributes['priceBookName'] = $account->get('priceBookName');
                $attributes['isTaxInclusive'] = $account->get('priceBookIsTaxInclusive');
            } else {
                $attributes['isTaxInclusive'] = $source->get('priceBookIsTaxInclusive');
            }

            if ($attributes['isTaxInclusive'] === null) {
                $attributes['isTaxInclusive'] = $this->defaultPriceBookProvider->get()?->isTaxInclusive() ?? false;
            }

            $attributes['billingAddressStreet'] = $account->get('billingAddressStreet');
            $attributes['billingAddressCity'] = $account->get('billingAddressCity');
            $attributes['billingAddressState'] = $account->get('billingAddressState');
            $attributes['billingAddressCountry'] = $account->get('billingAddressCountry');
            $attributes['billingAddressPostalCode'] = $account->get('billingAddressPostalCode');
            $attributes['shippingAddressStreet'] = $account->get('shippingAddressStreet');
            $attributes['shippingAddressCity'] = $account->get('shippingAddressCity');
            $attributes['shippingAddressState'] = $account->get('shippingAddressState');
            $attributes['shippingAddressCountry'] = $account->get('shippingAddressCountry');
            $attributes['shippingAddressPostalCode'] = $account->get('shippingAddressPostalCode');

            return $attributes;
        }

        return $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    public function copyItem(
        QuoteItem $item,
        ?float $defaultTaxRate,
        ?Link $defaultTaxCode,
        OrderEntity|Opportunity $source,
        string $targetType,
        ?Tax $tax = null,
    ): array {

        $sourceItemType = $item->getEntityType();

        $item->loadAllLinkMultipleFields();

        $itemAttributes = [
            'id' => '_' . $item->getId(), // Needed some ID for inventory quantity mapping.
            'name' => $item->getName(),
            'productId' => $item->getProduct()?->getId(),
            'productName' => $item->getProduct()?->getName(),
            'unitPrice' => $item->get('unitPrice'),
            'unitPriceCurrency' => $item->get('unitPriceCurrency'),
            'amount' => $item->get('amount'),
            'amountCurrency' => $item->get('amountCurrency'),
            'quantity' => $item->getQuantity(),
            'taxRate' => $item->get('taxRate') ?? $defaultTaxRate,
            'taxCodeId' => $item->get('taxCodeId') ?? $defaultTaxCode?->getId(),
            'taxCodeName' => $item->get('taxCodeName') ?? $defaultTaxCode?->getName(),
            'listPrice' => $item->get('listPrice') ?? $item->get('unitPrice'),
            'listPriceCurrency' => $item->get('amountCurrency'),
            'description' => $item->get('description'),
        ];

        if ($this->noListPrice($targetType)) {
            $itemAttributes[QuoteItem::FIELD_LIST_PRICE] = $itemAttributes[QuoteItem::FIELD_UNIT_PRICE];
        }

        $product = null;

        $productId = $item->getProduct()?->getId();

        if ($productId) {
            $product = $this->entityManager->getRDBRepositoryByClass(Product::class)->getById($productId);
        }

        if (
            $product &&
            $item->hasAttribute(QuoteItem::FIELD_LIST_PRICE) &&
            $item->get(QuoteItem::FIELD_LIST_PRICE) === null &&
            $this->configDataProvider->isProductLevelPricesEnabled()
        ) {
            $listPrice = $product->getListPrice()?->getAmount();
            $listPriceCurrency = $product->getListPrice()?->getCode();

            // @todo Use currency converter.
            if ($listPriceCurrency != $source->get('amountCurrency')) {
                $rates = $this->currencyConfig->getCurrencyRates()->toAssoc();
                $targetCurrency = $source->get('amountCurrency');

                $value = $listPrice;

                $rate1 = 1.0;

                if (array_key_exists($listPriceCurrency, $rates)) {
                    $rate1 = $rates[$listPriceCurrency];
                }

                $rate2 = 1.0;

                if (array_key_exists($targetCurrency, $rates)) {
                    $rate2 = $rates[$targetCurrency];
                }

                $value = $value * ($rate1);
                $value = $value / ($rate2);

                $listPrice = round($value, 2);
                $listPriceCurrency = $targetCurrency;
            }

            $itemAttributes[QuoteItem::FIELD_LIST_PRICE] = $listPrice;
            $itemAttributes[QuoteItem::FIELD_LIST_PRICE . 'Currency'] = $listPriceCurrency;
        }

        if (
            !$this->configDataProvider->isTaxCodesEnabled() &&
            $product &&
            $product->isTaxFree() &&
            $item->hasAttribute(QuoteItem::FIELD_TAX_RATE) &&
            $item->get(QuoteItem::FIELD_TAX_RATE) === null
        ) {
            $itemAttributes[QuoteItem::FIELD_TAX_RATE] = 0.0;
        }

        if (
            $tax &&
            $product &&
            $source instanceof Opportunity
        ) {
            $productTax = $this->productRateService->getProductTax($tax, $product);

            if ($productTax) {
                $itemAttributes[QuoteItem::FIELD_TAX_RATE] = $productTax->rate;
                $itemAttributes[QuoteItem::ATTR_TAX_CODE_ID] = $productTax->taxCode?->getId();
                $itemAttributes[QuoteItem::FIELD_TAX_CODE . 'Name'] = $productTax->taxCode?->getName();
            }
        }

        if ($product && $targetType === Invoice::ENTITY_TYPE) {
            $item->set(InvoiceItem::FIELD_PRODUCT_IS_SUBSCRIBABLE, $product->isSubscribable());
        }

        $attributeList = $this->entityManager
            ->getDefs()
            ->getEntity($sourceItemType)
            ->getAttributeNameList();

        foreach ($attributeList as $attribute) {
            if (
                !$item->hasAttribute($attribute) ||
                array_key_exists($attribute, $itemAttributes) ||
                in_array($attribute, $this->ignoreItemAttributeList)
            ) {
                continue;
            }

            $itemAttributes[$attribute] = $item->get($attribute);
        }

        return $itemAttributes;
    }

    /**
     * @return array<string, mixed>[]
     */
    private function getItems(OrderEntity|Opportunity $source, string $targetType, ?Tax $tax): array
    {
        if ($source instanceof Invoice && $targetType === Invoice::ENTITY_TYPE) {
            return [];
        }

        $taxRate = $tax?->getRate() ?? 0.0;
        $taxCode = $tax?->getTaxCodeLink();

        $sourceItemList = $this->getSourceItems($source);

        $salesOrderItems = null;

        if (
            $source instanceof SalesOrder &&
            $targetType === ReturnOrder::ENTITY_TYPE
        ) {
            if ($source->isDeliveryCreated()) {
                /** @var SalesOrderItem[] $salesOrderItems */
                $salesOrderItems = $sourceItemList;

                $sourceItemList = $this->getSalesOrderForReturnOrderSourceItems($source);
            }

            $sourceItemList = array_values(array_filter($sourceItemList, fn ($item) => (bool) $item->getProduct()));
        } else if (
            $source instanceof Opportunity &&
            $sourceItemList === [] &&
            $source->getAmount()?->getAmount()
        ) {
            $opportunityItem = $this->entityManager->getRDBRepositoryByClass(OpportunityItem::class)->getNew();

            $opportunityItem->setValueObject('amount', $source->getAmount());
            $opportunityItem->setValueObject('unitPrice', $source->getAmount());
            $opportunityItem->setQuantity(1.0);
            $opportunityItem->set('id', '1');
            $opportunityItem->setName($source->getName());

            $sourceItemList = [$opportunityItem];
        }

        $itemList = [];

        foreach ($sourceItemList as $item) {
            $itemList[] = $this->copyItem(
                item: $item,
                defaultTaxRate: $taxRate,
                defaultTaxCode: $taxCode,
                source: $source,
                targetType: $targetType,
                tax: $tax,
            );
        }

        if (
            $source instanceof SalesOrder &&
            $targetType === DeliveryOrder::ENTITY_TYPE
        ) {
            $itemList = $this->filterNonDeliverable($itemList, $targetType);
            $itemList = $this->filterAlreadyCreatedDelivery($source, $itemList);

            $itemList = $this->splitSerialItems($itemList);
        }

        if (
            $source instanceof SalesOrder &&
            $targetType === ReturnOrder::ENTITY_TYPE
        ) {
            $itemList = $this->filterAlreadyCreatedReturns($source, $itemList);
        }

        if ($salesOrderItems){
            $this->applyPricesFromSalesOrderItems(
                itemList: $itemList,
                salesOrderItems: $salesOrderItems,
                defaultTaxRate: $taxRate,
                defaultTaxCode: $taxCode,
                source: $source,
                targetType: $targetType,
            );
        }

        if (
            (
                $source instanceof PurchaseOrder ||
                $source instanceof ReturnOrder
            ) &&
            $targetType === ReceiptOrder::ENTITY_TYPE
        ) {
            $itemList = $this->filterNonDeliverable($itemList, $targetType);
        }

        if ($targetType === ReceiptOrder::ENTITY_TYPE) {
            foreach ($itemList as &$item) {
                $item['quantityReceived'] ??= null;
            }
        }

        return $itemList;
    }

    /**
     * @return QuoteItem[]
     */
    private function getSourceItems(OrderEntity|Opportunity $source): array
    {
        $sourceItemType = OrderEntityUtil::getItemEntityType($source->getEntityType());
        $idAttribute = lcfirst($source->getEntityType()) . 'Id';

        /** @var Collection<QuoteItem> $collection */
        $collection = $this->entityManager
            ->getRDBRepository($sourceItemType)
            ->where([$idAttribute => $source->getId()])
            ->order('order')
            ->find();

        return iterator_to_array($collection);
    }

    /**
     * @return QuoteItem[]
     */
    private function getSalesOrderForReturnOrderSourceItems(SalesOrder $source): array
    {
        /** @var string[] $doneStatusList */
        $doneStatusList = $this->metadata->get('scopes.DeliveryOrder.doneStatusList') ?? [];

        $deliveryOrders = $this->entityManager
            ->getRDBRepositoryByClass(DeliveryOrder::class)
            ->where([
                'salesOrderId' => $source->getId(),
                'status' => $doneStatusList,
            ])
            ->order('number')
            ->find();

        /** @var ReturnOrderItem[] $itemList */
        $itemList = [];

        foreach ($deliveryOrders as $deliveryOrder) {
            $itemList = array_merge(
                $itemList,
                $this->getSourceItems($deliveryOrder)
            );
        }

        return $itemList;
    }

    /**
     * @param array<string, mixed>[] $itemList
     * @param SalesOrderItem[] $salesOrderItems
     */
    private function applyPricesFromSalesOrderItems(
        array &$itemList,
        array $salesOrderItems,
        ?float $defaultTaxRate,
        ?Link $defaultTaxCode,
        OrderEntity|Opportunity $source,
        string $targetType,
    ): void {

        foreach ($itemList as &$item) {
            $productId = $item['productId'] ?? null;

            if (!$productId) {
                continue;
            }

            foreach ($salesOrderItems as $salesOrderItem) {
                if (
                    $salesOrderItem->getProduct() &&
                    $productId === $salesOrderItem->getProduct()->getId()
                ) {
                    $copiedItem = $this->copyItem(
                        item: $salesOrderItem,
                        defaultTaxRate: $defaultTaxRate,
                        defaultTaxCode: $defaultTaxCode,
                        source: $source,
                        targetType: $targetType,
                    );

                    $item['taxRate'] = $copiedItem['taxRate'] ?? 0.0;
                    $item['taxCodeId'] = $copiedItem['taxCodeId'] ?? null;
                    $item['taxCodeName'] = $copiedItem['taxCodeName'] ?? null;

                    $item['unitPrice'] = $copiedItem['unitPrice'] ?? null;
                    $item['unitPriceCurrency'] = $copiedItem['unitPriceCurrency'] ?? null;

                    $item['amountCurrency'] = $item['unitPriceCurrency'];
                    $item['amount'] = round($item['unitPrice'] * $item['quantity'], 2);

                    continue 2;
                }
            }

            $item['taxRate'] = 0.0;
            $item['unitPrice'] = 0.0;
            $item['unitPriceCurrency'] = $source->get('amountCurrency');
            $item['amountCurrency'] = $source->get('amountCurrency');
            $item['amount'] = 0.0;
        }
    }

    /**
     * @param array<string, mixed>[] $inputItems
     * @return array<string, mixed>[]
     */
    private function filterNonDeliverable(array $inputItems, string $targetType): array
    {
        $requireProduct = $this->entityManager
            ->getDefs()
            ->getEntity(OrderEntityUtil::getItemEntityType($targetType))
            ->getField(QuoteItem::FIELD_PRODUCT)
            ->getParam('required');

        $items = array_filter($inputItems, function ($it) use ($requireProduct) {
            $type = $it[SalesOrderItem::FIELD_ITEM_TYPE] ?? false;
            $productId = $it[QuoteItem::ATTR_PRODUCT_ID] ?? null;

            if ($productId === null && $requireProduct) {
                return false;
            }

            return $type === Product::ITEM_TYPE_GOODS;
        });

        return array_values($items);
    }

    /**
     * @param array<string, mixed>[] $inputItems
     * @return array<string, mixed>[]
     */
    private function filterAlreadyCreatedDelivery(SalesOrder $source, array $inputItems): array
    {
        $ignoreStatuses = array_merge(
            $this->metadata->get('scopes.DeliveryOrder.failedStatusList') ?? [],
            $this->metadata->get('scopes.DeliveryOrder.canceledStatusList') ?? [],
        );

        $deliveryOrders = $this->entityManager
            ->getRDBRepositoryByClass(DeliveryOrder::class)
            ->where([
                'salesOrderId' => $source->getId(),
                'status!=' => $ignoreStatuses,
            ])
            ->find();

        if (iterator_count($deliveryOrders) === 0) {
            return $inputItems;
        }

        [$items, $map] = $this->getQuantityMapAndItems($inputItems, $deliveryOrders, DeliveryOrderItem::ENTITY_TYPE);

        return $this->getFilteredItemsBasedOnQuantityMap($items, $map);
    }

    /**
     * @param array<string, mixed>[] $inputItems
     * @return array<string, mixed>[]
     */
    private function filterAlreadyCreatedReturns(SalesOrder $source, array $inputItems): array
    {
        $ignoreStatuses = array_merge(
            $this->metadata->get('scopes.ReturnOrder.canceledStatusList') ?? [],
        );

        /** @var Traversable<int, ReturnOrder> $returnOrders */
        $returnOrders = $this->entityManager
            ->getRDBRepositoryByClass(ReturnOrder::class)
            ->where([
                'salesOrderId' => $source->getId(),
                'status!=' => $ignoreStatuses,
            ])
            ->find();

        if (iterator_count($returnOrders) === 0) {
            return $inputItems;
        }

        [$items, $map] = $this->getQuantityMapAndItems($inputItems, $returnOrders, ReturnOrderItem::ENTITY_TYPE);

        return $this->getFilteredItemsBasedOnQuantityMap($items, $map);
    }

    /**
     * @param array<string, mixed>[] $inputItems
     * @param Traversable<int, OrderEntity> $orders
     * @param string $itemEntityType
     * @return array{QuoteItem[], array<string, float>}
     */
    private function getQuantityMapAndItems(array $inputItems, Traversable $orders, string $itemEntityType): array
    {
        $items = [];

        /** @var array<string, float> $map */
        $map = [];

        foreach ($inputItems as $rawItem) {
            /** @var QuoteItem $item */
            $item = $this->entityManager->getNewEntity($itemEntityType);
            $item->set($rawItem);

            if (!$item->getProduct()) {
                continue;
            }

            $productId = $item->getProduct()->getId();

            $map[$productId] ??= 0.0;
            $map[$productId] += $item->getQuantity();

            $items[] = $item;
        }

        foreach ($orders as $order) {
            $order->loadItemListField();

            foreach ($order->getItems() as $item) {
                if (!$item->getProductId()) {
                    continue;
                }

                $productId = $item->getProductId();

                if (!isset($map[$productId])) {
                    continue;
                }

                $map[$productId] -= $item->getQuantity();
            }
        }

        return [$items, $map];
    }

    /**
     * @param QuoteItem[] $items
     * @param array<string, float> $map
     * @return array<string, mixed>[]
     */
    private function getFilteredItemsBasedOnQuantityMap(array $items, array $map): array
    {
        $duplicateProductIds = [];

        /** @var QuoteItem[] $newItems */
        $newItems = [];

        foreach ($items as $item) {
            $productId = $item->getProduct()?->getId();

            if (!$productId) {
                continue;
            }

            foreach ($newItems as $newItem) {
                if ($newItem->getProduct()?->getId() === $productId) {
                    $duplicateProductIds[] = $productId;

                    continue 2;
                }
            }

            $newItems[] = $item;
        }

        $items = $newItems;

        $rawItems = [];

        foreach ($items as $item) {
            $productId = $item->getProduct()?->getId();

            if (!$productId) {
                continue;
            }

            $quantity = $map[$productId] ?? 0.0;

            if ($quantity === 0.0) {
                continue;
            }

            $rawItem = get_object_vars($item->getValueMap());
            $rawItem['quantity'] = $quantity;

            if (in_array($productId, $duplicateProductIds)) {
                unset($rawItem['inventoryNumberId']);
                unset($rawItem['inventoryNumberName']);
            }

            $rawItems[] = $rawItem;
        }

        return $rawItems;
    }

    /**
     * @param array<string, mixed>[] $itemList
     * @return array<string, mixed>[]
     */
    private function splitSerialItems(array $itemList): array
    {
        $output = [];

        $order = -1;

        foreach ($itemList as $rawItem) {
            $order ++;

            $item = $this->entityManager->getRDBRepositoryByClass(DeliveryOrderItem::class)->getNew();
            $item->set($rawItem);

            if ($item->getInventoryNumberType() !== InventoryNumber::TYPE_SERIAL) {
                $rawItem['order'] = $order;

                $output[] = $rawItem;

                continue;
            }

            $quantity = (int) $item->getQuantity();

            for ($i = 0; $i < $quantity; $i++) {
                $newItem = $rawItem;

                $newItem['quantity'] = 1.0;
                $newItem['quantityInt'] = 1;
                $newItem['order'] = $order;

                $newItem['id'] = $rawItem['id'] . '_' . $i;

                $output[] = $newItem;

                $order++;
            }
        }

        return $output;
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function addAccountPaymentMethods(Account $account, array $attributes): array
    {
        $this->paymentMethodLoader->process($account, new LoaderParams());

        if ($account->get('paymentMethodId')) {
            $attributes['paymentMethodsIds'] = $account->get('paymentMethodsIds');
            $attributes['paymentMethodsNames'] = $account->get('paymentMethodsNames');
            $attributes['paymentMethodsColumns'] = $account->get('paymentMethodsColumns');
            $attributes['paymentMethodId'] = $account->get('paymentMethodId');
            $attributes['paymentMethodName'] = $account->get('paymentMethodName');
        }

        return $attributes;
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function applyTaxAttributes(array $attributes, Tax $tax): array
    {
        $attributes['taxId'] = $tax->getId();
        $attributes['taxName'] = $tax->getName();
        $attributes['taxRate'] = $tax->getRate();
        $attributes['taxCodeId'] = $tax->getTaxCodeLink()?->getId();
        $attributes['taxCodeName'] = $tax->getTaxCodeLink()?->getName();
        $attributes['shippingTaxMode'] = $tax->getShippingMode();

        return $attributes;
    }

    private function getTax(Entity $source, string $targetType): ?Tax
    {
        if ($source instanceof TaxableOrder) {
            $tax = $source->getTax();

            if ($tax) {
                return $tax;
            }
        }

        if (
            // Sales.
            $source instanceof Opportunity ||
            $source instanceof Quote ||
            $source instanceof SalesOrder ||
            $source instanceof Invoice ||
            $source instanceof CreditNote ||
            $source instanceof ReturnOrder
        ) {
            $account = $source instanceof Opportunity ?
                $source->getAccount() :
                $source->getAccountEntity();

            if ($account) {
                $tax = $this->taxRuleService->get($account);

                if ($tax) {
                    return $tax;
                }
            }
        }

        $defaultTax = null;

        $defaultTaxId = $this->metadata->get("entityDefs.$targetType.fields.tax.defaultAttributes.taxId");

        if ($defaultTaxId) {
            $defaultTax = $this->entityManager->getRDBRepositoryByClass(Tax::class)->getById($defaultTaxId);
        }

        return $defaultTax;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function loadInventoryData(string $targetType, array &$attributes): void
    {
        if ($targetType !== SalesOrder::ENTITY_TYPE) {
            return;
        }

        $salesOrder = $this->entityManager->getRDBRepositoryByClass(SalesOrder::class)->getNew();

        $salesOrder->setMultiple($attributes);

        $this->inventoryDataLoader->process($salesOrder, LoaderParams::create());

        $attributes['inventoryQuantityMaps'] = $salesOrder->get('inventoryQuantityMaps');
        $attributes['inventoryData'] = $salesOrder->get('inventoryData');
    }

    private function noListPrice(string $targetType): bool
    {
        if (
            $targetType === PurchaseOrder::ENTITY_TYPE &&
            !$this->configDataProvider->isPurchaseOrderListPriceEnabled()
        ) {
            return true;
        }

        if (
            (
                $targetType === Invoice::ENTITY_TYPE ||
                $targetType === CreditNote::ENTITY_TYPE
            ) &&
            !$this->configDataProvider->isInvoiceListPriceEnabled()
        ) {
            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function filterAttributes(string $targetType, array &$attributes): void
    {
        $attributeList = $this->entityManager
            ->getDefs()
            ->getEntity($targetType)
            ->getAttributeNameList();

        foreach (array_keys($attributes) as $name) {
            if (!in_array($name, $attributeList)) {
                unset($attributes[$name]);
            }
        }
    }

    private function getSourceIdAttribute(string $targetType, string $sourceType): string
    {
        if ($targetType === Invoice::ENTITY_TYPE && $sourceType === Invoice::ENTITY_TYPE) {
            $idAttribute = Invoice::FIELD_PRECEDING_INVOICE . 'Id';
        } else {
            $idAttribute = lcfirst($sourceType) . 'Id';
        }

        return $idAttribute;
    }

    /**
     * @param array<string, mixed>[] $itemList
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function fixAmounts(OrderEntity|Opportunity $source, array $itemList, array $attributes): array
    {
        $amount = $source->get('amount');

        if (!$amount) {
            $amount = 0;
        }

        $preDiscountedAmount = 0;

        foreach ($itemList as $item) {
            $itemListPrice = $item['listPrice'] ?? 0.0;
            $itemQuantity = $item['quantity'] ?? 0.0;

            $preDiscountedAmount += $itemListPrice * $itemQuantity;
        }

        $preDiscountedAmount = round($preDiscountedAmount, 2);

        $attributes['preDiscountedAmount'] = $preDiscountedAmount;

        $discountAmount = $preDiscountedAmount - $amount;
        $attributes['discountAmount'] = $discountAmount;

        $grandTotalAmount = $amount + $attributes['taxAmount'] + $attributes['shippingCost'];
        $attributes['grandTotalAmount'] = $grandTotalAmount;

        return $attributes;
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function addPaymentTermsProfile(string $id, array $attributes): array
    {
        $profile = $this->entityManager->getRDBRepositoryByClass(PaymentTermsProfile::class)->getById($id);

        if (!$profile || !$profile->isActive()) {
            return $attributes;
        }

        $attributes[OrderEntity::FIELD_PAYMENT_TERMS_PROFILE . 'Id'] = $profile->getId();
        $attributes[OrderEntity::FIELD_PAYMENT_TERMS_PROFILE . 'Name'] = $profile->getName();

        $today = $this->dateTime->getToday();

        $dateDue = $this->termsCalculator->calculateDateDue($profile, $today);

        $attributes[Invoice::FIELD_DATE_DUE] = $dateDue->toString();

        return $attributes;
    }
}
