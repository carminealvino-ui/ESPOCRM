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

namespace Espo\Modules\Sales\Classes\FieldValidators\TaxCode\IncludedInPrice;

use Espo\Core\FieldValidation\Validator;
use Espo\Core\FieldValidation\Validator\Data;
use Espo\Core\FieldValidation\Validator\Failure;
use Espo\Core\Utils\Log;
use Espo\Modules\Sales\Entities\TaxCode;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Traversable;

/**
 * @implements Validator<TaxCode>
 */
class PriceInclusiveMustPrecedeInComposite implements Validator
{
    public function __construct(
        private EntityManager $entityManager,
        private Log $log,
    ) {}

    public function validate(Entity $entity, string $field, Data $data): ?Failure
    {
        if ($entity->isNew()) {
            return null;
        }

        /** @var Traversable<int, TaxCode> $parentTaxCodes */
        $parentTaxCodes = $this->entityManager
            ->getRelation($entity, TaxCode::LINK_PARENT_ITEMS)
            ->find();

        foreach ($parentTaxCodes as $parentTaxCode) {
            if ($entity->isIncludedInPrice() && $this->doesNotPrecede($parentTaxCode, $entity)) {
                $this->log->warning("Price code must precede in composite {$parentTaxCode->getId()}.");

                return Failure::create();
            }

            if (!$entity->isIncludedInPrice() && $this->isNotAfterInclusive($parentTaxCode, $entity)) {
                $this->log->warning("Price code must be after inclusive in composite {$parentTaxCode->getId()}.");

                return Failure::create();
            }
        }

        return null;
    }

    private function doesNotPrecede(TaxCode $parentTaxCode, TaxCode $taxCode): bool
    {
        foreach ($parentTaxCode->getItemCollection() as $it) {
            if ($it->getId() === $taxCode->getId()) {
                return false;
            }

            if (!$it->isIncludedInPrice()) {
                return true;
            }
        }

        return false;
    }

    private function isNotAfterInclusive(TaxCode $parentTaxCode, TaxCode $taxCode): bool
    {
        $met = false;

        foreach ($parentTaxCode->getItemCollection() as $it) {
            if ($it->getId() === $taxCode->getId()) {
                $met = true;

                continue;
            }

            if ($it->isIncludedInPrice()) {
                if ($met) {
                    return true;
                }
            }
        }

        return false;
    }
}
