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

use Espo\Core\Field\LinkParent;
use Espo\Modules\Crm\Entities\Opportunity;
use Espo\Modules\Sales\Entities\CreditNote;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Entities\Product;
use Espo\Modules\Sales\Entities\PurchaseOrder;
use Espo\Modules\Sales\Entities\Quote;
use Espo\Modules\Sales\Entities\QuoteItem;
use Espo\Modules\Sales\Entities\OpportunityItem;
use Espo\Modules\Sales\Entities\ReturnOrder;
use Espo\Modules\Sales\Entities\SalesOrder;
use Espo\Modules\Sales\Entities\SupplierBill;
use Espo\Modules\Sales\Entities\SupplierCredit;
use Espo\Modules\Sales\Entities\TaxLineItem;
use Espo\Modules\Sales\Entities\TaxTotalItem;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\Modules\Sales\Tools\Sales\OrderEntityUtil;
use Espo\ORM\Collection;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Name\Attribute;
use Espo\ORM\Query\DeleteBuilder;
use LogicException;
use stdClass;

class ItemsSaveProcessor
{
    private const ATTR_ACCOUNT_ID = 'accountId';
    private const ATTR_ACCOUNT_NAME = 'accountName';
    private const ATTR_AMOUNT_CURRENCY = 'amountCurrency';

    private const ATTR_ORDER = 'order';

    public function __construct(
        private EntityManager $entityManager,
    ) {}

    public function process(OrderEntity|Opportunity $quote, bool $isNew): void
    {
        if (!$quote->has(OrderEntity::ATTR_ITEM_LIST)) {
            $this->processNoItemsAccount($quote);

            return;
        }

        /** @var ?stdClass[] $itemList */
        $itemList = $quote->get(OrderEntity::ATTR_ITEM_LIST);

        if (!is_array($itemList)) {
            return;
        }

        $this->processSave($quote, $isNew, $itemList);
        $this->reloadItems($quote);
        $this->processSaveTaxItems($quote);

        $this->entityManager->getTransactionManager()->commit();
    }

    private function setItemWithData(
        QuoteItem|OpportunityItem $item,
        stdClass $raw,
        string $itemParentIdAttribute,
        ?string $currency,
    ): void {

        $data = [
            Attribute::ID => $raw->id ?? null,
            'name' => $this->getAttributeFromItemObject($raw, 'name'),
            'listPrice' => $this->getAttributeFromItemObject($raw, 'listPrice'),
            'unitPrice' => $this->getAttributeFromItemObject($raw, 'unitPrice'),
            'amount' => $this->getAttributeFromItemObject($raw, 'amount'),
            self::ATTR_AMOUNT_CURRENCY => $this->getAttributeFromItemObject($raw, self::ATTR_AMOUNT_CURRENCY),
            'taxRate' => $this->getAttributeFromItemObject($raw, 'taxRate'),
            'productId' => $this->getAttributeFromItemObject($raw, 'productId'),
            'productName' => $this->getAttributeFromItemObject($raw, 'productName'),
            'quantity' => $this->getAttributeFromItemObject($raw, 'quantity'),
            'unitWeight' => $this->getAttributeFromItemObject($raw, 'unitWeight'),
            'weight' => $this->getAttributeFromItemObject($raw, 'weight'),
            'description' => $this->getAttributeFromItemObject($raw, 'description'),
            'discount' => $this->getAttributeFromItemObject($raw, 'discount'),
        ];

        $this->setItemCurrencies($data, $currency);

        $ignoreAttributeList = [
            $itemParentIdAttribute,
            Attribute::ID,
            'name',
            'createdAt',
            'modifiedAt',
            'createdById',
            'createdByName',
            'modifiedById',
            'modifiedByName',
            'listPriceConverted',
            'unitPriceConverted',
            'amountConverted',
            Attribute::DELETED,
        ];

        $productAttributeList = $this->entityManager
            ->getNewEntity(Product::ENTITY_TYPE)
            ->getAttributeList();

        foreach ($productAttributeList as $attribute) {
            if (in_array($attribute, $ignoreAttributeList) || array_key_exists($attribute, $data)) {
                continue;
            }

            if (!$item->hasAttribute($attribute)) {
                continue;
            }

            $item->set($attribute, $this->getAttributeFromItemObject($raw, $attribute));

            if (
                $item->getAttributeType($attribute) === Entity::BOOL &&
                $item->get($attribute) === null
            ) {
                $item->set($attribute, false);
            }
        }

        foreach (get_object_vars($raw) as $attribute => $value) {
            if (array_key_exists($attribute, $data)) {
                continue;
            }

            if (in_array($attribute, $ignoreAttributeList)) {
                continue;
            }

            $data[$attribute] = $value;
        }

        $item->set($data);
    }

    private function getAttributeFromItemObject(stdClass $data, string $attribute): mixed
    {
        return $data->$attribute ?? null;
    }

    private function startTransactionAndLock(string $id, string $itemEntityType, string $itemParentIdAttribute): void
    {
        $this->entityManager
            ->getTransactionManager()
            ->start();

        $this->entityManager
            ->getRDBRepository($itemEntityType)
            ->sth()
            ->select(Attribute::ID)
            ->forUpdate()
            ->where([$itemParentIdAttribute => $id])
            ->find();
    }

    private function isItemChanged(string $itemEntityType, QuoteItem $prevItem, stdClass $rawItem): bool
    {
        $seed = $this->entityManager->getNewEntity($itemEntityType);
        $seed->set($prevItem->getValueMap());
        $seed->setAsFetched();
        $seed->setAsNotNew();
        $seed->set($rawItem);

        foreach ($seed->getAttributeList() as $attr) {
            if ($prevItem->getAttributeType($attr) === Entity::FOREIGN) {
                continue;
            }

            if ($seed->isAttributeChanged($attr)) {
                return true;
            }

            /*
            $v1 = $seed->get($attr);
            $v0 = $prevItem->get($attr);
            //$bothNumeric = is_numeric($v1) && is_numeric($v0);

            if (
                ($bothNumeric && abs($v1 - $v0) > 0.00001) || // @todo Revise.
                (!$bothNumeric && $seed->isAttributeChanged($attr))
            ) {
                return true;
            }
            */
        }

        return false;
    }

    private function processNoItemsAccount(OrderEntity|Opportunity $quote): void
    {
        if (!$quote->isAttributeChanged(self::ATTR_ACCOUNT_ID)) {
            return;
        }

        $items = $this->loadItems($quote);

        foreach ($items as $item) {
            $item->set(self::ATTR_ACCOUNT_ID, $quote->get(self::ATTR_ACCOUNT_ID));

            $this->entityManager->saveEntity($item);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function setItemCurrencies(array &$data, ?string $currency): void
    {
        $currencyAttributeList = [
            'listPrice',
            'unitPrice',
            'amount',
        ];

        foreach ($currencyAttributeList as $attribute) {
            if ($data[$attribute] === null) {
                $data[$attribute . 'Currency'] = null;

                continue;
            }

            $data[$attribute . 'Currency'] = $currency ?? $data[self::ATTR_AMOUNT_CURRENCY] ?? null;
        }
    }

    /**
     * @param stdClass[] $itemList
     */
    private function processSave(OrderEntity|Opportunity $quote, bool $isNew, array $itemList): void
    {
        $itemEntityType = $this->getItemEntityType($quote);
        $itemParentIdAttribute = $this->getParentIdAttribute($quote);

        /** @var ?string $currency */
        $currency = $quote->get(self::ATTR_AMOUNT_CURRENCY);

        $toCreateList = [];
        $toUpdateList = [];
        $toRemoveList = [];

        $this->startTransactionAndLock($quote->getId(), $itemEntityType, $itemParentIdAttribute);

        if (!$isNew) {
            $prevItems = $this->loadItems($quote, true);

            foreach ($prevItems as $item) {
                $exists = false;

                foreach ($itemList as $data) {
                    if ($item->getId() === ($data->id ?? null)) {
                        $exists = true;
                    }
                }

                if (!$exists) {
                    $toRemoveList[] = $item;
                }
            }

            $quote->setFetched(OrderEntity::ATTR_ITEM_LIST, $prevItems->getValueMapList());
        }

        $order = 0;

        foreach ($itemList as $rawItem) {
            $itId = $rawItem->id ?? null;

            $order++;
            $exists = false;

            if (!$isNew) {
                foreach ($prevItems as $prevItem) {
                    /** @var QuoteItem $prevItem */

                    if ($itId !== $prevItem->getId()) {
                        continue;
                    }

                    $isChanged = $this->isItemChanged($itemEntityType, $prevItem, $rawItem);

                    if (!$isChanged && $prevItem->getOrder() !== $order) {
                        $isChanged = true;
                    }

                    $exists = true;

                    if (!$isChanged) {
                        break;
                    }

                    $this->setItemWithData(
                        item: $prevItem,
                        raw: $rawItem,
                        itemParentIdAttribute: $itemParentIdAttribute,
                        currency: $currency,
                    );

                    $prevItem->set(self::ATTR_ORDER, $order);
                    $prevItem->set($itemParentIdAttribute, $quote->getId());
                    $prevItem->set(self::ATTR_ACCOUNT_ID, $quote->get(self::ATTR_ACCOUNT_ID));
                    $prevItem->set(self::ATTR_ACCOUNT_NAME, $quote->get(self::ATTR_ACCOUNT_NAME));

                    $toUpdateList[] = $prevItem;

                    break;
                }
            }

            if ($exists) {
                continue;
            }

            /** @var QuoteItem|OpportunityItem $item */
            $item = $this->entityManager->getNewEntity($itemEntityType);

            $this->setItemWithData(
                item: $item,
                raw: $rawItem,
                itemParentIdAttribute: $itemParentIdAttribute,
                currency: $currency,
            );

            $item->set(self::ATTR_ORDER, $order);
            $item->set($itemParentIdAttribute, $quote->getId());
            $item->set(self::ATTR_ACCOUNT_ID, $quote->get(self::ATTR_ACCOUNT_ID));
            $item->set(self::ATTR_ACCOUNT_NAME, $quote->get(self::ATTR_ACCOUNT_NAME));

            /** @noinspection PhpRedundantOptionalArgumentInspection */
            $item->set(Attribute::ID, null);

            $toCreateList[] = $item;
        }

        if ($isNew) {
            foreach ($toUpdateList as $item) {
                /** @noinspection PhpRedundantOptionalArgumentInspection */
                $item->set(Attribute::ID, null);

                $toCreateList[] = $item;
            }

            $toUpdateList = [];
        }

        foreach ($toRemoveList as $item) {
            $this->entityManager->removeEntity($item);
        }

        foreach ($toUpdateList as $item) {
            $this->entityManager->saveEntity($item);
        }

        foreach ($toCreateList as $item) {
            $this->entityManager->saveEntity($item);
        }
    }

    private function getItemEntityType(OrderEntity|Opportunity $quote): string
    {
        return OrderEntityUtil::getItemEntityType($quote->getEntityType());
    }

    private function getParentIdAttribute(OrderEntity|Opportunity $quote): string
    {
        return lcfirst($quote->getEntityType()) . 'Id';
    }

    /**
     * @return Collection<QuoteItem|OpportunityItem>
     */
    private function loadItems(OrderEntity|Opportunity $quote, bool $full = false): Collection
    {
        $itemEntityType = $this->getItemEntityType($quote);
        $itemParentIdAttribute = $this->getParentIdAttribute($quote);

        /** @var Collection<QuoteItem|OpportunityItem> $items */
        $items = $this->entityManager
            ->getRDBRepository($itemEntityType)
            ->where([$itemParentIdAttribute => $quote->getId()])
            ->order(self::ATTR_ORDER)
            ->find();

        if ($full) {
            foreach ($items as $item) {
                $item->loadAllLinkMultipleFields();
            }
        }

        return $items;
    }

    private function reloadItems(OrderEntity|Opportunity $quote): void
    {
        $items = $this->loadItems($quote, true);

        $quote->set(OrderEntity::ATTR_ITEM_LIST, $items->getValueMapList());
    }

    private function processSaveTaxItems(OrderEntity|Opportunity $order): void
    {
        if (
            !(
                $order instanceof Quote ||
                $order instanceof SalesOrder ||
                $order instanceof Invoice ||
                $order instanceof CreditNote ||
                $order instanceof ReturnOrder ||
                $order instanceof PurchaseOrder ||
                $order instanceof SupplierBill ||
                $order instanceof SupplierCredit
            ) ||
            !OrderEntityUtil::isWithTax($order->getEntityType())
        ) {
            return;
        }

        if ($order->getTaxLineSaveItems() === null) {
            return;
        }

        if (!$order->isNew()) {
            $this->deleteTaxLineItems($order);
            $this->deleteTaxTotalItems($order);
        }

        $this->saveTaxLineItems($order);
        $this->saveTaxTotalItems($order);

        $order->clearTaxSaveItems();
    }

    private function deleteTaxLineItems(OrderEntity $order): void
    {
        $query = DeleteBuilder::create()
            ->from(TaxLineItem::ENTITY_TYPE)
            ->where([
                TaxLineItem::FIELD_SOURCE . 'Id' => $order->getId(),
                TaxLineItem::FIELD_SOURCE . 'Type' => $order->getEntityType(),
            ])
            ->build();

        $this->entityManager->getQueryExecutor()->execute($query);
    }

    private function deleteTaxTotalItems(OrderEntity $order): void
    {
        $query = DeleteBuilder::create()
            ->from(TaxTotalItem::ENTITY_TYPE)
            ->where([
                TaxTotalItem::FIELD_SOURCE . 'Id' => $order->getId(),
                TaxTotalItem::FIELD_SOURCE . 'Type' => $order->getEntityType(),
            ])
            ->build();

        $this->entityManager->getQueryExecutor()->execute($query);
    }

    private function saveTaxLineItems(
        ReturnOrder|CreditNote|Invoice|Quote|PurchaseOrder|SalesOrder|SupplierBill|SupplierCredit $order,
    ): void {

        $saveItems = $order->getTaxLineSaveItems() ?? throw new LogicException();

        $itemEntityType = OrderEntityUtil::getItemEntityType($order->getEntityType());

        $items = $order->getItems();

        foreach ($saveItems as $i => $saveItem) {
            $taxLineItem = $saveItem->taxLineItem;

            if (
                $taxLineItem->getComponent() === TaxLineItem::COMPONENT_ITEM &&
                $saveItem->index !== null
            ) {
                $item = $items[$saveItem->index] ?? null;

                if ($item && $item->getId()) {
                    $taxLineItem->setItem(LinkParent::create($itemEntityType, $item->getId()));
                }
            }

            $taxLineItem
                ->setOrder($i)
                ->setSource($order);

            $this->entityManager->saveEntity($taxLineItem);
        }
    }

    private function saveTaxTotalItems(
        ReturnOrder|CreditNote|Invoice|Quote|PurchaseOrder|SalesOrder|SupplierBill|SupplierCredit $order
    ): void {

        foreach ($order->getTaxTotalSaveItems() ?? [] as $i => $totalItem) {
            $totalItem
                ->setOrder($i)
                ->setSource($order);

            $this->entityManager->saveEntity($totalItem);
        }
    }
}
