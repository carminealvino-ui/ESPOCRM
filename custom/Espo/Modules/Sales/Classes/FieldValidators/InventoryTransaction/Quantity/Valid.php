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

namespace Espo\Modules\Sales\Classes\FieldValidators\InventoryTransaction\Quantity;

use Espo\Core\FieldValidation\Validator;
use Espo\Core\FieldValidation\Validator\Data;
use Espo\Core\FieldValidation\Validator\Failure;
use Espo\Modules\Sales\Entities\InventoryNumber;
use Espo\Modules\Sales\Entities\InventoryTransaction;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * @implements Validator<InventoryTransaction>
 */
class Valid implements Validator
{
    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function validate(Entity $entity, string $field, Data $data): ?Failure
    {
        if (!$entity->has('quantity')) {
            return null;
        }

        if (
            !in_array($entity->getType(), [
                InventoryTransaction::TYPE_RESERVE,
                InventoryTransaction::TYPE_TRANSFER,
            ])
        ) {
            return null;
        }

        if (!$entity->getInventoryNumber()) {
            return null;
        }

        $number = $this->entityManager
            ->getRDBRepositoryByClass(InventoryNumber::class)
            ->getById($entity->getInventoryNumber()->getId());

        if (!$number) {
            return null;
        }

        if ($number->getType() !== InventoryNumber::TYPE_SERIAL) {
            return null;
        }

        $quantity = $entity->getQuantity();

        if (
            $quantity === 1.0 ||
            $quantity === -1.0
        ) {
            return null;
        }

        return Failure::create();
    }
}
