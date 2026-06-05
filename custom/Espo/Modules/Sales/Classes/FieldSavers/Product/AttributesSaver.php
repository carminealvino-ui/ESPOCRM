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

namespace Espo\Modules\Sales\Classes\FieldSavers\Product;

use Espo\Core\FieldProcessing\Saver;
use Espo\Core\FieldProcessing\Saver\Params;
use Espo\Modules\Sales\Entities\Product;
use Espo\Modules\Sales\Entities\ProductAttribute;
use Espo\Modules\Sales\Entities\ProductAttributeOption;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * @implements Saver<Product>
 * @noinspection PhpUnused
 */
class AttributesSaver implements Saver
{
    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function process(Entity $entity, Params $params): void
    {
        if ($entity->getType() !== Product::TYPE_TEMPLATE) {
            return;
        }

        if (!$entity->hasAttributeItems()) {
            return;
        }

        if (!$entity->isAttributeChanged('attributes')) {
            return;
        }

        /** @var iterable<ProductAttribute> $attributes */
        $attributes = $this->entityManager
            ->getRDBRepositoryByClass(Product::class)
            ->getRelation($entity, 'attributes')
            ->select(['id', 'name'])
            ->order('order')
            ->find();

        /** @var iterable<ProductAttributeOption> $options */
        $options = $this->entityManager
            ->getRDBRepositoryByClass(Product::class)
            ->getRelation($entity, 'attributeOptions')
            ->select(['id', 'name', 'attributeId'])
            ->order('order')
            ->find();

        $attributeIds = [];
        $optionIds = [];
        $newAttributeIds = [];
        $newOptionIds = [];

        foreach ($attributes as $attribute) {
            $attributeIds[] = $attribute->getId();
        }

        foreach ($options as $option) {
            $optionIds[] = $option->getId();
        }

        foreach ($entity->getAttributeItems() as $attributeItem) {
            $newAttributeIds[] = $attributeItem->getId();

            foreach ($attributeItem->getOptions() as $optionItem) {
                $newOptionIds[] = $optionItem->getId();
            }
        }

        $attributesToAdd = array_diff($newAttributeIds, $attributeIds);
        $attributesToRemove = array_diff($attributeIds, $newAttributeIds);

        $optionsToAdd = array_diff($newOptionIds, $optionIds);
        $optionsToRemove = array_diff($optionIds, $newOptionIds);

        $repository = $this->entityManager->getRDBRepositoryByClass(Product::class);

        foreach ($optionsToRemove as $id) {
            $repository
                ->getRelation($entity, 'attributeOptions')
                ->unrelateById($id);
        }

        foreach ($attributesToRemove as $id) {
            $repository
                ->getRelation($entity, 'attributes')
                ->unrelateById($id);
        }

        foreach ($attributesToAdd as $id) {
            $repository
                ->getRelation($entity, 'attributes')
                ->relateById($id);
        }

        foreach ($optionsToAdd as $id) {
            $repository
                ->getRelation($entity, 'attributeOptions')
                ->relateById($id);
        }
    }
}
