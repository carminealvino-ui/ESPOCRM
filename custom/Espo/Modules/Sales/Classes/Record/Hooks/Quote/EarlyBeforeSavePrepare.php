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

namespace Espo\Modules\Sales\Classes\Record\Hooks\Quote;

use Espo\Core\Acl;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Record\Hook\SaveHook;
use Espo\Core\Utils\FieldUtil;
use Espo\Core\Utils\Metadata;
use Espo\Modules\Crm\Entities\Opportunity;
use Espo\Modules\Sales\Entities\CreditNote;
use Espo\Modules\Sales\Entities\InventoryNumber;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Entities\InvoiceItem;
use Espo\Modules\Sales\Entities\Product;
use Espo\Modules\Sales\Entities\Quote;
use Espo\Modules\Sales\Entities\ReturnOrder;
use Espo\Modules\Sales\Entities\SalesOrder;
use Espo\Modules\Sales\Tools\Sales\ConfigDataProvider;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\Modules\Sales\Tools\Sales\OrderEntityUtil;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use stdClass;

/**
 * Prepares product attributes in items.
 *
 * @implements SaveHook<OrderEntity|Opportunity>
 */
class EarlyBeforeSavePrepare implements SaveHook
{
    public function __construct(
        private EntityManager $entityManager,
        private Metadata $metadata,
        private FieldUtil $fieldUtil,
        private Acl $acl,
        private ConfigDataProvider $configDataProvider,
    ) {}

    public function process(Entity $entity): void
    {
        $this->processItems($entity);
    }

    /**
     * @throws BadRequest
     * @throws Forbidden
     */
    private function processItems(OrderEntity|Opportunity $entity): void
    {
        if (
            !$entity->has(OrderEntity::ATTR_ITEM_LIST) ||
            !$entity->isAttributeWritten(OrderEntity::ATTR_ITEM_LIST)
        ) {
            return;
        }

        $items = $entity->get(OrderEntity::ATTR_ITEM_LIST);

        if (!is_array($items)) {
            return;
        }

        $previousIdsMap = $this->getPreviousIdsMap($entity);

        foreach ($items as $item) {
            if (!$item instanceof stdClass) {
                throw new BadRequest("Bad item.");
            }

            $this->prepareItem($item, $previousIdsMap, $entity->getEntityType());
        }

        $entity->set(OrderEntity::ATTR_ITEM_LIST, $items);
    }

    /**
     * @param stdClass $item
     * @param array{'product': string[], 'inventoryNumber': string[]} $previousMap
     * @throws BadRequest
     * @throws Forbidden
     */
    private function prepareItem(stdClass $item, array $previousMap, string $entityType): void
    {
        $this->prepareTaxFields($item, $entityType);

        $this->checkInventoryNumber($item, $previousMap['inventoryNumber']);

        $product = $this->getProductAndProcess($item, $previousMap['product']);

        $this->prepareItemPeriod($item, $entityType, $product);

        if (!$product) {
            return;
        }

        $this->copyItemProductFields($entityType, $item, $product);
    }

    /**
     * @param string[] $previousIds
     * @throws BadRequest
     * @throws Forbidden
     */
    private function getProductAndProcess(stdClass $item, array $previousIds): ?Product
    {
        $productId = $this->getProductId($item);

        if (!$productId) {
            return null;
        }

        $product = $this->entityManager->getRDBRepositoryByClass(Product::class)->getById($productId);

        $this->processItemProduct($item, $productId, $product, $previousIds);

        return $product;
    }

    /**
     * @return string[]
     */
    private function getCopyFieldList(string $itemEntityType): array
    {
        return $this->metadata->get("entityDefs.$itemEntityType.fields.product.copyFieldList") ?? [];
    }

    /**
     * @param OrderEntity|Opportunity $entity
     * @return array{'product': string[], 'inventoryNumber': string[]}
     */
    private function getPreviousIdsMap(OrderEntity|Opportunity $entity): array
    {
        /** @var stdClass[] $previousItems */
        $previousItems = $entity->getFetched(OrderEntity::ATTR_ITEM_LIST) ?? [];

        $previousProductIds = [];
        $previousInventoryNumbersIds = [];

        foreach ($previousItems as $previousItem) {
            $itProductId = $previousItem->productId ?? null;
            $itInventoryNumberId = $previousItem->inventoryNumberId ?? null;

            if ($itProductId) {
                $previousProductIds[] = $itProductId;
            }

            if ($itInventoryNumberId) {
                $previousInventoryNumbersIds[] = $itInventoryNumberId;
            }
        }

        return [
            'product' => $previousProductIds,
            'inventoryNumber' => $previousInventoryNumbersIds,
        ];
    }

    /**
     * @param string[] $previousIds
     * @throws BadRequest
     * @throws Forbidden
     */
    private function processItemProduct(stdClass $item, string $productId, ?Product $product, array $previousIds): void
    {
        if (!$product) {
            throw new BadRequest("Product $productId does not exist.");
        }

        $item->name = $product->getName();

        if (in_array($productId, $previousIds)) {
            return;
        }

        if (!$this->acl->checkEntityRead($product)) {
            throw new Forbidden("No access to product $productId.");
        }
    }

    /**
     * @throws BadRequest
     */
    private function getProductId(stdClass $item): ?string
    {
        $productId = $item->productId ?? null;

        if (!is_string($productId) && $productId !== null) {
            throw new BadRequest("Bad product ID");
        }

        return $productId;
    }

    /**
     * @param stdClass $item
     * @param string[] $previousIds
     * @throws BadRequest
     * @throws Forbidden
     */
    private function checkInventoryNumber(stdClass $item, array $previousIds): void
    {
        $inventoryNumberId = $item->inventoryNumberId ?? null;

        if (!is_string($inventoryNumberId) && $inventoryNumberId !== null) {
            throw new BadRequest("Bad inventory number ID.");
        }

        if (!$inventoryNumberId || in_array($inventoryNumberId, $previousIds)) {
            return;
        }

        $number = $this->entityManager->getRDBRepositoryByClass(InventoryNumber::class)->getById($inventoryNumberId);

        if (!$number) {
            throw new BadRequest("Inventory number $inventoryNumberId does not exist.");
        }

        if (!$this->acl->checkEntityRead($number)) {
            throw new Forbidden("No access to inventory number $inventoryNumberId.");
        }
    }

    /**
     * @param string[] $copyFieldList
     * @param stdClass $item
     * @return string[]
     */
    private function getCopyAttributeList(array $copyFieldList, stdClass $item): array
    {
        $attributeList = [];

        foreach ($copyFieldList as $field) {
            $itemAttributeList = $this->fieldUtil->getActualAttributeList(Product::ENTITY_TYPE, $field);

            $has = false;

            foreach ($itemAttributeList as $attribute) {
                if (property_exists($item, $attribute)) {
                    $has = true;

                    break;
                }
            }

            if ($has) {
                continue;
            }

            $attributeList = array_merge($attributeList, $itemAttributeList);
        }

        return $attributeList;
    }

    private function copyItemProductFields(string $entityType, stdClass $item, Product $product): void
    {
        $itemEntityType = $this->getItemEntityType($entityType);

        $copyFieldList = $this->getCopyFieldList($itemEntityType);
        $attributeList = $this->getCopyAttributeList($copyFieldList, $item);

        foreach ($attributeList as $attribute) {
            $item->$attribute = $product->get($attribute);
        }
    }

    private function prepareItemPeriod(stdClass $item, string $entityType, ?Product $product): void
    {
        if (
            $entityType !== Invoice::ENTITY_TYPE &&
            $entityType !== CreditNote::ENTITY_TYPE
        ) {
            return;
        }

        if (!$product || !$product->isSubscribable()) {
            $item->{InvoiceItem::FIELD_PERIOD_START_DATE} = null;
            $item->{InvoiceItem::FIELD_PERIOD_END_DATE} = null;
        }
    }

    private function getItemEntityType(string $entityType): string
    {
        return OrderEntityUtil::getItemEntityType($entityType);
    }

    private function prepareTaxFields(stdClass $item, string $entityType): void
    {
        if (!$this->configDataProvider->isTaxCodesEnabled()) {
            unset($item->taxCodeId);
        }

        if (
            $this->configDataProvider->isTaxCodesEnabled() &&
            OrderEntityUtil::isWithTax($entityType)
        ) {
            $item->taxRate = null;
        }
    }
}
