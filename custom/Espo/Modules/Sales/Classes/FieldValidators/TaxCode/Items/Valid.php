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

namespace Espo\Modules\Sales\Classes\FieldValidators\TaxCode\Items;

use Espo\Core\FieldValidation\Validator;
use Espo\Core\FieldValidation\Validator\Data;
use Espo\Core\FieldValidation\Validator\Failure;
use Espo\Modules\Sales\Entities\TaxCode;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use stdClass;

/**
 * @implements Validator<TaxCode>
 */
class Valid implements Validator
{
    public function __construct(
        private EntityManager $entityManager,
    ) {}

    public function validate(Entity $entity, string $field, Data $data): ?Failure
    {
        $value = $entity->get(TaxCode::FIELD_ITEMS);

        if ($value === null) {
            return null;
        }

        if (!is_array($value)) {
            return Failure::create();
        }

        foreach ($value as $item) {
            if (!$item instanceof stdClass) {
                return Failure::create();
            }

            $id = $item->id ?? null;
            $name = $item->name ?? null;

            if (!is_string($id)) {
                return Failure::create();
            }

            if (!is_string($name) && $name !== null) {
                return Failure::create();
            }

            $taxCode = $this->entityManager->getRDBRepositoryByClass(TaxCode::class)->getById($id);

            if (!$taxCode) {
                return Failure::create();
            }
        }

        return null;
    }
}
