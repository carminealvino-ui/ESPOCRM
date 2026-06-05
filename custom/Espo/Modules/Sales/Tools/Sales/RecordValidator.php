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

namespace Espo\Modules\Sales\Tools\Sales;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Error\Body;
use Espo\Core\FieldValidation\Exceptions\ValidationError;
use Espo\Core\FieldValidation\FieldValidationManager;
use Espo\Core\Utils\FieldUtil;
use Espo\Core\Utils\Metadata;
use Espo\Modules\Crm\Entities\Opportunity;
use Espo\Modules\Sales\Entities\DeliveryOrder;
use Espo\Modules\Sales\Entities\DeliveryOrderItem;
use Espo\Modules\Sales\Entities\InventoryAdjustment;
use Espo\Modules\Sales\Entities\InventoryAdjustmentItem;
use Espo\Modules\Sales\Entities\PaymentEntry;
use Espo\Modules\Sales\Entities\PaymentRequest;
use Espo\Modules\Sales\Entities\Product;
use Espo\Modules\Sales\Entities\QuoteItem;
use Espo\Modules\Sales\Entities\ReceiptOrder;
use Espo\Modules\Sales\Entities\ReceiptOrderItem;
use Espo\Modules\Sales\Entities\SalesOrder;
use Espo\Modules\Sales\Entities\TransferOrder;
use Espo\Modules\Sales\Entities\TransferOrderItem;
use Espo\Modules\Sales\Entities\WriteOffEntry;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use RuntimeException;
use stdClass;
use Traversable;

class RecordValidator
{
    public function __construct(
        private EntityManager $entityManager,
        private FieldValidationManager $validationManager,
        private Metadata $metadata,
        private FieldUtil $fieldUtil,
    ) {}

    /**
     * @throws Conflict
     * @throws BadRequest
     */
    public function process(Opportunity|OrderEntity $entity): void
    {
        if ($entity instanceof OrderEntity) {
            $this->validateLocked($entity);
        }

        if (
            !$entity->isAttributeChanged(OrderEntity::FIELD_STATUS) &&
            !$entity->isAttributeChanged(OrderEntity::ATTR_ITEM_LIST)
        ) {
            return;
        }

        $this->loadValidItemAttributesAndProductValidation($entity);

        $itemEntityType = $this->getItemEntityType($entity);

        $map = $this->getPreviousItemMap($entity, $itemEntityType);

        foreach ($this->getItemList($entity) as $i => $item) {
            $id = $item->id ?? null;

            $itemEntity = $id && array_key_exists($id, $map) ?
                $map[$id] :
                $this->entityManager->getNewEntity($itemEntityType);

            $itemEntity->set($item);

            if (!$itemEntity instanceof QuoteItem) {
                throw new RuntimeException("Wrong item instance.");
            }

            $this->validateItem($itemEntity, $item, $i);
            $this->validateItemQuantityReceived($entity, $itemEntity);
            $this->validateItemInventoryNumber($entity, $itemEntity);
        }
    }

    private function checkItemQuantityReceived(Entity $item, ReceiptOrder|TransferOrder $order): bool
    {
        if (!in_array($order->getStatus(), $this->getDoneStatusList($order->getEntityType()))) {
            return true;
        }

        return $item->get('quantityReceived') !== null;
    }

    private function checkInventoryNumber(
        DeliveryOrderItem|TransferOrderItem|InventoryAdjustmentItem $item,
        DeliveryOrder|TransferOrder|InventoryAdjustment $order,
    ): bool {

        if (
            $order->getEntityType() !== InventoryAdjustment::ENTITY_TYPE &&
            in_array(
                $order->getStatus(),
                $this->getSoftReservedCanceledStatusList($order->getEntityType())
            )
        ) {
            return true;
        }

        if (!$item->getProduct()) {
            return true;
        }

        $product = $this->entityManager
            ->getRDBRepositoryByClass(Product::class)
            ->getById($item->getProduct()->getId());

        if (!$product) {
            return true;
        }

        if (!$product->getInventoryNumberType()) {
            return true;
        }

        return $item->getInventoryNumber() !== null;
    }

    /**
     * @return string[]
     */
    private function getDoneStatusList(string $entityType): array
    {
        return $this->metadata->get("scopes.$entityType.doneStatusList") ?? [];
    }

    /**
     * @return string[]
     */
    private function getSoftReservedCanceledStatusList(string $entityType): array
    {
        return array_merge(
            $this->metadata->get("scopes.$entityType.softReserveStatusList") ?? [],
            $this->metadata->get("scopes.$entityType.canceledStatusList") ?? [],
        );
    }

    /**
     * @throws BadRequest
     */
    private function loadValidItemAttributesAndProductValidation(OrderEntity|Opportunity $entity): void
    {
        $itemList = $this->getItemList($entity);

        if ($itemList === []) {
            return;
        }

        $productMap = $this->getProductMap($entity);

        foreach ($productMap as $product) {
            if ($product->getType() === Product::TYPE_TEMPLATE) {
                throw BadRequest::createWithBody(
                    'Product template cannot be selected in an order item.',
                    Body::create()
                        ->withMessageTranslation('productTemplateCannotBeSelected', 'Quote', [
                            'name' => $product->getName(),
                        ])
                        ->encode()
                );
            }

            if (
                $product->getItemType() !== Product::ITEM_TYPE_GOODS &&
                (
                    $entity instanceof ReceiptOrder ||
                    $entity instanceof TransferOrder ||
                    $entity instanceof DeliveryOrder
                )
            ) {
                throw BadRequest::createWithBody(
                    'onlyGoodsProductAllowed',
                    Body::create()
                        ->withMessageTranslation('onlyGoodsProductAllowed', 'Quote')
                        ->encode()
                );
            }
        }

        $this->loadValidItemProductAttributes($productMap, $entity);

        if (
            !$entity instanceof SalesOrder &&
            !$entity instanceof ReceiptOrder &&
            !$entity instanceof TransferOrder &&
            !$entity instanceof DeliveryOrder
        ) {
            return;
        }

        $this->loadValidItemInventoryAttributes($productMap, $entity);
    }

    /**
     * @return array<string, Product>
     */
    private function getProductMap(OrderEntity|Opportunity $entity): array
    {
        $productIds = $entity instanceof Opportunity ?
            $this->getProductIds($entity) :
            $entity->getProductIds();

        /** @var iterable<Product> $products */
        $products = $this->entityManager
            ->getRDBRepositoryByClass(Product::class)
            ->where(['id' => $productIds])
            ->find();

        $map = [];

        foreach ($products as $product) {
            $map[$product->getId()] = $product;
        }

        return $map;
    }

    /**
     * @return string[]
     */
    private function getProductIds(Opportunity $entity): array
    {
        $ids = [];

        foreach ($entity->get(OrderEntity::ATTR_ITEM_LIST) ?? [] as $item) {
            $productId = $item->productId ?? null;

            if (!$productId || in_array($productId, $ids)) {
                continue;
            }

            $ids[] = $productId;
        }

        return $ids;
    }

    /**
     * @param array<string, Product> $productMap
     */
    private function loadValidItemInventoryAttributes(
        array $productMap,
        DeliveryOrder|TransferOrder|SalesOrder|ReceiptOrder $entity
    ): void {

        $itemList = $this->getItemList($entity);

        if ($itemList === []) {
            return;
        }

        $toSet = false;

        foreach ($itemList as $item) {
            $productId = $item->productId ?? null;

            if (!$productId) {
                continue;
            }

            $product = $productMap[$productId] ?? null;

            if (!$product) {
                continue;
            }

            $item->inventoryNumberType ??= null;
            $item->isInventory ??= null;

            if (
                $item->inventoryNumberType !== $product->getInventoryNumberType() ||
                $item->isInventory !== $product->isInventory()
            ) {
                $toSet = true;

                $item->inventoryNumberType = $product->getInventoryNumberType();
                $item->isInventory = $product->isInventory();
            }
        }

        if (!$toSet) {
            return;
        }

        $entity->set(OrderEntity::ATTR_ITEM_LIST, $itemList);
    }

    /**
     * @param array<string, Product> $productMap
     */
    private function loadValidItemProductAttributes(array $productMap, OrderEntity|Opportunity $entity): void
    {
        $itemList = $this->getItemList($entity);

        if ($itemList === []) {
            return;
        }

        $toSet = false;

        foreach ($itemList as $item) {
            $productId = $item->productId ?? null;

            if (!$productId) {
                continue;
            }

            $product = $productMap[$productId] ?? null;

            if (!$product) {
                continue;
            }

            $item->allowFractionalQuantity ??= null;

            if (
                $item->allowFractionalQuantity !== $product->allowFractionalQuantity()
            ) {
                $toSet = true;

                $item->allowFractionalQuantity = $product->allowFractionalQuantity();
            }
        }

        if ($toSet) {
            $entity->set(OrderEntity::ATTR_ITEM_LIST, $itemList);
        }
    }

    /**
     * Validate lockable fields not changed in a locked record.
     *
     * @throws Conflict
     */
    public function validateLocked(OrderEntity|PaymentEntry|PaymentRequest|WriteOffEntry $entity): void
    {
        if (!$entity->isLocked()) {
            return;
        }

        /** @var string[] $fieldList */
        $fieldList = $this->metadata->get("scopes.{$entity->getEntityType()}.lockableFieldList") ?? [];

        $changedField = null;

        foreach ($fieldList as $field) {
            foreach ($this->fieldUtil->getActualAttributeList($entity->getEntityType(), $field) as $attribute) {
                if ($entity->isAttributeChanged($attribute)) {
                    $changedField = $field;

                    break;
                }
            }
        }

        if ($changedField === null) {
            return;
        }

        throw Conflict::createWithBody(
            "Can't modify the locked record.",
            Body::create()
                ->withMessageTranslation('cantModifyLocked', 'Quote', ['field' => $changedField])
                ->encode()
        );
    }

    /**
     * @param string $itemEntityType
     * @return ?Traversable<int, QuoteItem>
     */
    private function getPreviousItems(
        Opportunity|OrderEntity $entity,
        string $itemEntityType
    ): ?Traversable {

        if ($entity->isNew()) {
            return null;
        }

        $idAttr = lcfirst($entity->getEntityType()) . 'Id';

        /** @var Traversable<int, QuoteItem> */
        return $this->entityManager
            ->getRDBRepository($itemEntityType)
            ->where([$idAttr => $entity->getId()])
            ->find();
    }

    private function getItemEntityType(Opportunity|OrderEntity $entity): string
    {
        return OrderEntityUtil::getItemEntityType($entity->getEntityType());
    }

    /**
     * @return stdClass[]
     */
    private function getItemList(Opportunity|OrderEntity $entity): array
    {
        /** @var stdClass[] */
        return $entity->get(OrderEntity::ATTR_ITEM_LIST) ?? [];
    }

    /**
     * @return array<string, QuoteItem>
     */
    private function getPreviousItemMap(Opportunity|OrderEntity $entity, string $itemEntityType): array
    {
        $previousItems = $this->getPreviousItems($entity, $itemEntityType);

        /** @var array<string, QuoteItem> $map */
        $map = [];

        foreach (($previousItems ?? []) as $it) {
            $map[$it->getId()] = $it;
        }

        return $map;
    }

    /**
     * @throws BadRequest
     */
    private function validateItemQuantityReceived(Opportunity|OrderEntity $entity, QuoteItem $itemEntity): void
    {
        if (
            !$entity instanceof ReceiptOrder &&
            !$entity instanceof TransferOrder
        ) {
            return;
        }

        /** @var ReceiptOrderItem|TransferOrderItem $itemEntity */

        if (!$this->checkItemQuantityReceived($itemEntity, $entity)) {
            throw BadRequest::createWithBody(
                'Required quantity received.',
                Body::create()
                    ->withMessageTranslation('requiredQuantityReceived', 'Quote')
                    ->encode()
            );
        }
    }

    /**
     * @throws BadRequest
     */
    private function validateItemInventoryNumber(Opportunity|OrderEntity $entity, QuoteItem $itemEntity): void
    {
        if (
            !$entity instanceof DeliveryOrder &&
            !$entity instanceof TransferOrder &&
            !$entity instanceof InventoryAdjustment
        ) {
            return;
        }

        /** @var DeliveryOrderItem|TransferOrderItem|InventoryAdjustmentItem $itemEntity */

        if (!$this->checkInventoryNumber($itemEntity, $entity)) {
            throw BadRequest::createWithBody(
                'Required inventory number.',
                Body::create()
                    ->withMessageTranslation('requiredInventoryNumber', 'Quote')
                    ->encode()
            );
        }
    }

    /**
     * @throws BadRequest
     */
    private function validateItem(QuoteItem $itemEntity, stdClass $item, int $i): void
    {
        try {
            $this->validationManager->process($itemEntity, $item);
        } catch (ValidationError $e) {
            throw BadRequest::createWithBody(
                $e->getLogMessage(),
                Body::create()
                    ->withMessageTranslation('invalidItems', 'Quote', [
                        'number' => (string) ($i + 1),
                        'type' => $e->getFailure()->getType(),
                        'field' => $e->getFailure()->getField(),
                    ])
            );
        }
    }
}
