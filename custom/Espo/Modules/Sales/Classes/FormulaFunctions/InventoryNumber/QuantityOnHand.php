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

namespace Espo\Modules\Sales\Classes\FormulaFunctions\InventoryNumber;

use Espo\Core\Formula\EvaluatedArgumentList;
use Espo\Core\Formula\Exceptions\BadArgumentType;
use Espo\Core\Formula\Exceptions\TooFewArguments;
use Espo\Core\Formula\Func;
use Espo\Modules\Sales\Entities\InventoryNumber;
use Espo\Modules\Sales\Tools\Product\Quantity\ApplierParams;
use Espo\Modules\Sales\Tools\Product\Quantity\QuantitySelectApplier;
use Espo\ORM\EntityManager;
use Espo\ORM\Query\SelectBuilder;

/**
 * @noinspection PhpUnused
 */
class QuantityOnHand implements Func
{
    public function __construct(
        private EntityManager $entityManager,
        private QuantitySelectApplier $quantitySelectApplier,
    ) {}

    public function process(EvaluatedArgumentList $arguments): float
    {
        if (count($arguments) < 1) {
            throw TooFewArguments::create(1);
        }

        $id = $arguments[0];
        $warehouseId = $arguments[1] ?? null;

        if (!is_string($id)) {
            throw BadArgumentType::create(1, 'string');
        }

        if ($warehouseId !== null && !is_string($warehouseId)) {
            throw BadArgumentType::create(2, 'string');
        }

        $queryBuilder = SelectBuilder::create()
            ->from(InventoryNumber::ENTITY_TYPE);

        $params = new ApplierParams(
            type: ApplierParams::TYPE_ON_HAND,
            warehouseId: $warehouseId,
            isNumber: true,
        );

        $this->quantitySelectApplier->apply($queryBuilder, $params);

        $product = $this->entityManager
            ->getRDBRepositoryByClass(InventoryNumber::class)
            ->clone($queryBuilder->build())
            ->where(['id' => $id])
            ->findOne();

        if (!$product) {
            return 0.0;
        }

        return $product->get('quantityOnHand') ?? 0.0;
    }
}
