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

namespace Espo\Modules\Sales\Tools\Product;

use Espo\Core\Utils\FieldUtil;
use Espo\Modules\Sales\Entities\Product;
use Espo\Modules\Sales\Tools\ProductAttribute\OptionItem;
use Espo\ORM\EntityManager;

/**
 * @noinspection PhpUnused
 */
class VariantCreator
{
    public function __construct(
        private EntityManager $entityManager,
        private FieldUtil $fieldUtil,
        private MetadataProvider $metadataProvider
    ) {}

    /**
     * @param OptionItem[] $options
     */
    public function create(Product $template, array $options, int $index): Product
    {
        $variant = $this->entityManager
            ->getRDBRepositoryByClass(Product::class)
            ->getNew();

        $name = $this->prepareName($template, $options);

        $variant->set('name', $name);

        $this->sync($template, $variant);

        $variant->set('templateId', $template->getId());
        $variant->set('variantOrder', $index);

        $variant->set('brandId', $template->get('brandId'));
        $variant->set('categoryId', $template->get('categoryId'));
        $variant->set('isTaxFree', $template->isTaxFree());

        $variant
            ->setType(Product::TYPE_VARIANT)
            ->setIsInventory($template->isInventory())
            ->setStatus($template->getStatus())
            ->setInventoryNumberType($template->getInventoryNumberType())
            ->setAllowFractionalQuantity($template->allowFractionalQuantity());

        foreach ($options as $item) {
            $variant->addLinkMultipleId('variantAttributeOptions', $item->getId());
        }

        $this->entityManager->saveEntity($variant);

        return $variant;
    }

    /**
     * @param OptionItem[] $options
     */
    private function prepareName(Product $template, array $options): string
    {
        $name = $template->getName() .
            ' · ' .
            implode(' · ', array_map(fn($it) => $it->getName(), $options));

        $maxNameLength = $this->entityManager
            ->getDefs()
            ->getEntity(Product::ENTITY_TYPE)
            ->getAttribute('name')
            ->getLength() ?? 255;

        return trim(substr($name, 0, $maxNameLength));
    }

    private function sync(Product $template, Product $variant): void
    {
        foreach ($this->getSyncAttributes() as $attribute) {
            $variant->set($attribute, $template->get($attribute));
        }
    }

    /**
     * @return string[]
     */
    private function getSyncAttributes(): array
    {
        $fields = $this->metadataProvider->getVariantSyncFieldList();

        $attributes = [];

        foreach ($fields as $field) {
            $attributes = array_merge(
                $attributes,
                $this->fieldUtil->getActualAttributeList(Product::ENTITY_TYPE, $field)
            );
        }

        return $attributes;
    }
}
