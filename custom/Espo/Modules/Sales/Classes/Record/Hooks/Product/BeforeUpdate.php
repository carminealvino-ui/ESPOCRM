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

namespace Espo\Modules\Sales\Classes\Record\Hooks\Product;

use Espo\Core\Record\Hook\UpdateHook;
use Espo\Core\Record\UpdateParams;
use Espo\Core\Utils\FieldUtil;
use Espo\Modules\Sales\Entities\Product;
use Espo\Modules\Sales\Tools\Product\MetadataProvider;
use Espo\ORM\Entity;

/**
 * @implements UpdateHook<Product>
 * @noinspection PhpUnused
 */
class BeforeUpdate implements UpdateHook
{
    public function __construct(
        private FieldUtil $fieldUtil,
        private MetadataProvider $metadataProvider
    ) {}

    public function process(Entity $entity, UpdateParams $params): void
    {
        if ($entity->getType() !== Product::TYPE_TEMPLATE) {
            $entity->clear('attributes');
        }

        if ($entity->getType() === Product::TYPE_VARIANT) {
            $this->revertChangesForVariant($entity);
        }
    }

    private function revertChangesForVariant(Product $entity): void
    {
        $fields = $this->metadataProvider->getVariantSyncFieldList();

        foreach ($fields as $field) {
            $attributes = $this->fieldUtil->getAttributeList(Product::ENTITY_TYPE, $field);

            foreach ($attributes as $attribute) {
                if ($entity->isAttributeChanged($attribute)) {
                    $entity->set($attribute, $entity->getFetched($attribute));
                }
            }
        }
    }
}
