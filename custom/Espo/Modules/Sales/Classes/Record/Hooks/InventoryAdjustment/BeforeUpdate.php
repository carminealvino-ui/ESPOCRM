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

namespace Espo\Modules\Sales\Classes\Record\Hooks\InventoryAdjustment;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Record\Hook\UpdateHook;
use Espo\Core\Record\UpdateParams;
use Espo\Modules\Sales\Entities\InventoryAdjustment;
use Espo\Modules\Sales\Entities\Product;
use Espo\Modules\Sales\Tools\InventoryAdjustment\AvailabilityCheck;
use Espo\Modules\Sales\Tools\InventoryAdjustment\ValidationHelper;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\Modules\Sales\Tools\Sales\OrderItem;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use RuntimeException;

/**
 * @implements UpdateHook<InventoryAdjustment>
 * @noinspection PhpUnused
 */
class BeforeUpdate implements UpdateHook
{
    public function __construct(
        private AvailabilityCheck $availabilityCheck,
        private ValidationHelper $validationHelper,
        private EntityManager $entityManager
    ) {}

    public function process(Entity $entity, UpdateParams $params): void
    {
        if ($entity->getFetchedStatus() === InventoryAdjustment::STATUS_COMPLETED) {
            $this->processCompleted($entity);
        }

        if ($entity->isAttributeChanged(OrderEntity::ATTR_ITEM_LIST)) {
            $this->processItemList($entity);
        }

        $this->processNewQuantity($entity);
        $this->processAvailability($entity);
    }

    /**
     * @throws BadRequest
     */
    private function processAvailability(InventoryAdjustment $entity): void
    {
        if (!$this->validationHelper->toValidateAvailability($entity)) {
            return;
        }

        if ($this->availabilityCheck->check($entity)) {
            return;
        }

        throw BadRequest::createWithBody(
            'Same items in another adjustment.',
            Body::create()
                ->withMessageTranslation('sameItemsInAnotherAdjustment', InventoryAdjustment::ENTITY_TYPE)
                ->encode()
        );
    }

    /**
     * @throws Conflict
     */
    private function processCompleted(InventoryAdjustment $entity): void
    {
        if ($entity->getStatus() !== InventoryAdjustment::STATUS_COMPLETED) {
            throw new Conflict("Can't change status of already completed adjustment.");
        }

        $entity->clear(OrderEntity::ATTR_ITEM_LIST);
    }

    /**
     * @throws BadRequest
     */
    private function processItemList(InventoryAdjustment $entity): void
    {
        $metKeys = [];

        foreach ($entity->getItems() as $item) {
            $key = ($item->getProductId() ?? '-') . '-' . ($item->getInventoryNumberId() ?? '');

            // @todo Check product is inventory.

            $this->checkIsInventory($item);

            if (in_array($key, $metKeys)) {
                throw BadRequest::createWithBody(
                    'Duplicate items.',
                    Body::create()
                        ->withMessageTranslation('duplicateItems', InventoryAdjustment::ENTITY_TYPE)
                        ->encode()
                );
            }

            $metKeys[] = $key;
        }
    }

    /**
     * @throws BadRequest
     */
    private function processNewQuantity(InventoryAdjustment $entity): void
    {
        if ($entity->getStatus() !== InventoryAdjustment::STATUS_COMPLETED) {
            return;
        }

        foreach ($entity->getItems() as $item) {
            if ($item->get('newQuantityOnHand') === null) {
                throw BadRequest::createWithBody(
                    'Empty new quantity in item.',
                    Body::create()
                        ->withMessageTranslation('emptyNewQuantityInItem', InventoryAdjustment::ENTITY_TYPE)
                        ->encode()
                );
            }
        }
    }

    /**
     * @throws BadRequest
     */
    private function checkIsInventory(OrderItem $item): void
    {
        if (!$item->getProductId()) {
            throw new RuntimeException("No product ID.");
        }

        $product = $this->entityManager
            ->getRDBRepositoryByClass(Product::class)
            ->getById($item->getProductId());

        if (!$product) {
            throw new RuntimeException("Not found product {$item->getProductId()}.");
        }

        if ($product->isInventory()) {
            return;
        }

        throw BadRequest::createWithBody(
            'Product is not inventory.',
            Body::create()
                ->withMessageTranslation('itemProductIsNotInventory', InventoryAdjustment::ENTITY_TYPE, [
                    'name' => $product->getName(),
                ])
                ->encode()
        );
    }
}
