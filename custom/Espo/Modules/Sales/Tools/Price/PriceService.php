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

use Espo\Core\Acl;
use Espo\Core\Currency\Converter;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Field\Currency;
use Espo\Modules\Crm\Entities\Account;
use Espo\Modules\Crm\Entities\Opportunity;
use Espo\Modules\Sales\Entities\CreditNote;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Entities\PriceBook;
use Espo\Modules\Sales\Entities\Product;
use Espo\Modules\Sales\Entities\PurchaseOrder;
use Espo\Modules\Sales\Entities\Quote;
use Espo\Modules\Sales\Entities\ReturnOrder;
use Espo\Modules\Sales\Entities\SalesOrder;
use Espo\Modules\Sales\Entities\Subscription;
use Espo\Modules\Sales\Entities\SubscriptionBillingPlan;
use Espo\Modules\Sales\Entities\SubscriptionUpdate;
use Espo\Modules\Sales\Entities\Supplier;
use Espo\Modules\Sales\Tools\Price\Sales\Data;
use Espo\Modules\Sales\Tools\Price\Service\PurchaseData;
use Espo\Modules\Sales\Tools\Price\Service\SalesData;
use Espo\Modules\Sales\Tools\Product\ProductQuantityPair;
use Espo\Modules\Sales\Tools\Quote\RoundingUtil;
use Espo\Modules\Sales\Tools\Sales\ConfigDataProvider;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class PriceService
{
    public function __construct(
        private EntityManager $entityManager,
        private Acl $acl,
        private PriceProvider $priceProvider,
        private PurchasePriceProvider $purchasePriceProvider,
        private Converter $converter,
        private ConfigDataProvider $configDataProvider,
        private RoundingUtil $roundingUtil,
    ) {}

    /**
     * @param ProductQuantityPair[] $pairs
     * @return PricePair[]
     * @throws NotFound
     * @throws Forbidden
     */
    public function getSalesMultiple(
        array $pairs,
        ?string $priceBookId = null,
        ?SalesData $data = null,
    ): array {

        if ($priceBookId && !$this->configDataProvider->isPriceBooksEnabled()) {
            throw new Forbidden("Price books functionality is not enabled.");
        }

        return array_map(fn ($pair) => $this->getSales($pair, $priceBookId, $data), $pairs);
    }

    /**
     * @param ProductQuantityPair[] $pairs
     * @return PricePair[]
     * @throws NotFound
     * @throws Forbidden
     */
    public function getPurchaseMultiple(array $pairs, PurchaseData $data): array
    {
        return array_map(fn ($pair) => $this->getPurchase($pair, $data), $pairs);
    }

    /**
     * @throws NotFound
     * @throws Forbidden
     */
    private function getSales(
        ProductQuantityPair $pair,
        ?string $priceBookId,
        ?SalesData $data = null,
    ): PricePair {

        $this->checkSalesAccess($data);

        $product = $this->getProduct($pair);
        $account = $this->getAccount($data);
        $priceBook = $this->getPriceBook($priceBookId, $account, $data);
        $interval = $this->getInterval($data);

        $providerData = new Data(
            account: $account,
            interval: $interval,
        );

        $pricePair = $this->priceProvider->get(
            product: $product,
            quantity: $pair->getQuantity(),
            priceBook: $priceBook,
            data: $providerData,
        );

        $pricePair = $this->convertPricePair($pricePair, $data->currency);

        if ($this->toNullList($data)) {
            $pricePair = $pricePair->withList(null);
        }

        return $pricePair;
    }

    /**
     * @throws Forbidden
     * @throws NotFound
     */
    private function getPurchase(ProductQuantityPair $pair, PurchaseData $data): PricePair
    {
        $product = $this->getProduct($pair);
        $supplier = $this->getSupplier($data->supplierId);

        if (
            !$supplier &&
            !$this->acl->checkField(Product::ENTITY_TYPE, 'costPrice') &&
            !$this->acl->checkScope(PurchaseOrder::ENTITY_TYPE)
        ) {
            throw new Forbidden("No access to PurchaseOrder and costPrice field.");
        }

        $pricePair = $this->purchasePriceProvider->get($product, $pair->getQuantity(), $supplier);

        $pricePair = $this->convertPricePair($pricePair, $data->currency);

        if ($this->toNullList($data)) {
            $pricePair = $pricePair->withList(null);
        }

        return $pricePair;
    }

    /**
     * @throws Forbidden
     * @throws NotFound
     */
    private function getProduct(ProductQuantityPair $pair): Product
    {
        $productId = $pair->getProductId();

        /** @var ?Product $product */
        $product = $this->entityManager->getEntityById(Product::ENTITY_TYPE, $productId);

        if (!$product) {
            throw new NotFound("Product '$productId' not found.");
        }

        if (!$this->acl->checkEntityRead($product)) {
            throw new Forbidden("No access to Product '$productId'.");
        }

        return $product;
    }

    private function convertPricePair(PricePair $pricePair, ?string $currency): PricePair
    {
        if (!$currency) {
            return $pricePair;
        }

        return new PricePair(
            $pricePair->getUnit() ? $this->convertPriceItem($pricePair->getUnit(), $currency) : null,
            $pricePair->getList() ? $this->convertPriceItem($pricePair->getList(), $currency) : null
        );
    }

    private function convertPriceItem(Currency $price, string $currency): Currency
    {
        if ($price->getCode() === $currency) {
            return $price;
        }

        $price = $this->converter->convert($price, $currency);

        return $this->roundingUtil->round($price);
    }

    /**
     * Returns only if a user has access either to an Account or to an Order.
     */
    private function getAccount(?SalesData $data): ?Account
    {
        if (!$data || !$data->accountId) {
            return null;
        }

        $account = $this->entityManager->getRDBRepositoryByClass(Account::class)->getById($data->accountId);

        if (!$account) {
            return null;
        }

        if ($this->acl->checkEntityRead($account)) {
            return $account;
        }

        $order = $this->getOrder($data);

        if (!$order) {
            return null;
        }

        if ($order->get(OrderEntity::ATTR_ACCOUNT_ID) !== $account->getId()) {
            return null;
        }

        if ($this->acl->checkEntityRead($order)) {
            return $account;
        }

        return null;
    }

    private function getOrder(SalesData $data): ?Entity
    {
        if (!$data->orderId || !$data->orderType) {
            return null;
        }

        return $this->entityManager->getEntityById($data->orderType, $data->orderId);
    }

    /**
     * @throws Forbidden
     */
    private function checkSalesAccess(?SalesData $data): void
    {
       if (
           (
               $data->orderType === Subscription::ENTITY_TYPE ||
               $data->orderType === SubscriptionUpdate::ENTITY_TYPE
           ) &&
           !$data->billingPlanId
       ) {
           throw new Forbidden("No billing plan ID.");
       }

        if (
            (
                $data->orderType !== Subscription::ENTITY_TYPE &&
                $data->orderType !== SubscriptionUpdate::ENTITY_TYPE
            ) &&
            $data->billingPlanId
        ) {
            throw new Forbidden("Non-subscription type.");
        }

        if (
            $data->orderType &&
            $this->acl->checkScope($data->orderType) &&
            in_array($data->orderType, [
                Quote::ENTITY_TYPE,
                SalesOrder::ENTITY_TYPE,
                Invoice::ENTITY_TYPE,
                ReturnOrder::ENTITY_TYPE,
                Opportunity::ENTITY_TYPE,
                CreditNote::ENTITY_TYPE,
                Subscription::ENTITY_TYPE,
            ])
        ) {
            return;
        }

        if (
            !$this->acl->checkScope(PriceBook::ENTITY_TYPE) &&
            !$this->acl->checkField(Product::ENTITY_TYPE, 'unitPrice')
        ) {
            throw new Forbidden("No access to both PriceBook and unitPrice field.");
        }
    }

    /**
     * @throws NotFound
     * @throws Forbidden
     */
    private function getPriceBook(?string $priceBookId, ?Account $account, ?SalesData $data): ?PriceBook
    {
        if (!$priceBookId) {
            return null;
        }

        $priceBook = $this->entityManager->getRDBRepositoryByClass(PriceBook::class)->getById($priceBookId);

        if (!$priceBook) {
            throw new NotFound("Price Book '$priceBookId' not found.");
        }

        if (!$priceBook->isActive()) {
            throw new Forbidden("Price Book '$priceBookId' not active.");
        }

        if ($account && $account->get(OrderEntity::ATTR_PRICE_BOOK_ID) === $priceBook->getId()) {
            return $priceBook;
        }

        if ($priceBook->getId() === $this->configDataProvider->getDefaultPriceBookId()) {
            return $priceBook;
        }

        if ($this->acl->checkEntityRead($priceBook)) {
            return $priceBook;
        }

        if (!$data) {
            throw new Forbidden("No access to Price Book '$priceBookId'.");
        }

        $order = $this->getOrder($data);

        if ($order && $order->get(OrderEntity::ATTR_PRICE_BOOK_ID) === $priceBook->getId()) {
            return $priceBook;
        }

        throw new Forbidden("No access to Price Book '$priceBookId'.");
    }

    /**
     * @throws NotFound
     */
    private function getInterval(SalesData $data): ?string
    {
        if (!$data->billingPlanId) {
            return null;
        }

        $plan = $this->entityManager
            ->getRDBRepositoryByClass(SubscriptionBillingPlan::class)
            ->getById($data->billingPlanId);

        if (!$plan) {
            throw new NotFound("Billing plan not found.");
        }

        return $plan->getInterval();
    }

    private function toNullList(SalesData|PurchaseData|null $data): bool
    {
        if ($data instanceof PurchaseData) {
            if (
                $data->orderType === PurchaseOrder::ENTITY_TYPE &&
                !$this->configDataProvider->isPurchaseOrderListPriceEnabled()
            ) {
                return true;
            }

            return false;
        }

        return (
            $data?->orderType === Invoice::ENTITY_TYPE ||
            $data?->orderType === CreditNote::ENTITY_TYPE
        ) &&
        !$this->configDataProvider->isInvoiceListPriceEnabled();
    }

    /**
     * @throws Forbidden
     * @throws NotFound
     */
    private function getSupplier(?string $supplierId): ?Supplier
    {
        $supplier = $supplierId ?
            $this->entityManager->getRDBRepositoryByClass(Supplier::class)->getById($supplierId) :
            null;

        if ($supplierId && !$supplier) {
            throw new NotFound("Supplier '$supplierId' not found.");
        }

        if ($supplier && !$this->acl->checkEntityRead($supplier)) {
            throw new Forbidden("No access to Supplier '$supplierId'.");
        }

        return $supplier;
    }
}
