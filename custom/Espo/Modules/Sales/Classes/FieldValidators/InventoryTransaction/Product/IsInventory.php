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

namespace Espo\Modules\Sales\Classes\FieldValidators\InventoryTransaction\Product;

use Espo\Core\FieldValidation\Validator;
use Espo\Core\FieldValidation\Validator\Data;
use Espo\Core\FieldValidation\Validator\Failure;
use Espo\Modules\Sales\Entities\InventoryTransaction;
use Espo\Modules\Sales\Entities\Product;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * @implements Validator<InventoryTransaction>
 */
class IsInventory implements Validator
{
    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function validate(Entity $entity, string $field, Data $data): ?Failure
    {
        $id = $entity->get('productId');

        if (!$id) {
            return null;
        }

        /** @var ?Product $product */
        $product = $this->entityManager->getEntityById(Product::ENTITY_TYPE, $id);

        if (!$product) {
            return null;
        }

        if ($product->isInventory() && $product->getType() !== Product::TYPE_TEMPLATE) {
            return null;
        }

        return Failure::create();
    }
}
