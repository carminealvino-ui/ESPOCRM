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

namespace Espo\Modules\Sales\Tools\Inventory;

use Espo\Modules\Sales\Entities\DeliveryOrder;
use Espo\Modules\Sales\Entities\InventoryNumber;
use Espo\Modules\Sales\Entities\Product;
use Espo\Modules\Sales\Entities\TransferOrder;
use Espo\Modules\Sales\Tools\Product\Quantity\ApplierParams;
use Espo\Modules\Sales\Tools\Product\Quantity\QuantitySelectApplier;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\ORM\Collection;
use Espo\ORM\EntityManager;
use Espo\ORM\Query\SelectBuilder;

class ProductQuantityLoader
{
    public function __construct(
        private EntityManager $entityManager,
        private QuantitySelectApplier $quantitySelectApplier,
    ) {}

    /**
     * Load inventory quantities for specified products.
     *
     * @param string[] $productIds
     * @return array<string, float>
     */
    public function load(
        array $productIds,
        ?OrderEntity $entity = null,
        bool $excludeSoftReserve = false,
        bool $forceTotal = false,
    ): array {

        $warehouseId = null;

        $parentId = null;
        $parentType = null;

        if ($entity && $entity->hasId()) {
            $parentType = $entity->getEntityType();
            $parentId = $entity->getId();
        }

        if (
            $entity instanceof DeliveryOrder &&
            $entity->getWarehouse() &&
            !$forceTotal
        ) {
            $warehouseId = $entity->getWarehouse()->getId();
        }

        if (
            $entity instanceof TransferOrder &&
            !$forceTotal
        ) {
            $warehouseId = $entity->getFromWarehouse()->getId();
        }

        $applierParams = new ApplierParams(
            parentType: $parentType,
            parentId: $parentId,
            warehouseId: $warehouseId,
            excludeSoftReserve: $excludeSoftReserve,
        );

        $builder = SelectBuilder::create()
            ->from(Product::ENTITY_TYPE)
            ->select(['id'])
            ->where(['id' => $productIds]);

        $this->quantitySelectApplier->apply($builder, $applierParams);

        $products = $this->entityManager
            ->getRDBRepositoryByClass(Product::class)
            ->clone($builder->build())
            ->find();

        $map = [];

        foreach ($products as $product) {
            /** @var float $quantity */
            $quantity = $product->get('quantity') ?? 0.0;

            $map[$product->getId()] = $quantity;
        }

        return $map;
    }

    /**
     * @param string[] $numberIds
     * @return array<string, float>
     */
    public function loadForNumbers(array $numberIds, DeliveryOrder|TransferOrder $entity): array
    {
        $warehouseId = null;

        if (
            $entity instanceof DeliveryOrder &&
            $entity->getWarehouse()
        ) {
            $warehouseId = $entity->getWarehouse()->getId();
        }

        if ($entity instanceof TransferOrder) {
            $warehouseId = $entity->getFromWarehouse()->getId();
        }

        $applierParams = new ApplierParams(
            type: ApplierParams::TYPE_ON_HAND,
            parentType: $entity->getEntityType(),
            parentId: $entity->hasId() ? $entity->getId() : null,
            warehouseId: $warehouseId,
            isNumber: true,
        );

        $builder = SelectBuilder::create()
            ->from(InventoryNumber::ENTITY_TYPE)
            ->select(['id'])
            ->where(['id' => $numberIds]);

        $this->quantitySelectApplier->apply($builder, $applierParams);

        /** @var Collection<InventoryNumber> $numbers */
        $numbers = $this->entityManager
            ->getRDBRepository(InventoryNumber::ENTITY_TYPE)
            ->clone($builder->build())
            ->find();

        $map = [];

        foreach ($numbers as $number) {
            /** @var InventoryNumber $number */

            /** @var float $quantity */
            $quantity = $number->get('quantityOnHand') ?? 0.0;

            $map[$number->getId()] = $quantity;
        }

        return $map;
    }
}
