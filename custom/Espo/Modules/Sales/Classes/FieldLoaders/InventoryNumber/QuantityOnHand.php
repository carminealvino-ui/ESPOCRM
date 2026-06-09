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

namespace Espo\Modules\Sales\Classes\FieldLoaders\InventoryNumber;

use Espo\Core\FieldProcessing\Loader;
use Espo\Core\FieldProcessing\Loader\Params;
use Espo\Modules\Sales\Entities\InventoryNumber;
use Espo\Modules\Sales\Tools\Product\Quantity\ApplierParams;
use Espo\Modules\Sales\Tools\Product\Quantity\QuantitySelectApplier;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Name\Attribute;
use Espo\ORM\Query\SelectBuilder;

/**
 * @implements Loader<InventoryNumber>
 */
class QuantityOnHand implements Loader
{
    protected string $field = 'quantityOnHand';

    /**
     * @var ApplierParams::TYPE_*
     * @noinspection PhpDocFieldTypeMismatchInspection
     */
    protected int $type = ApplierParams::TYPE_ON_HAND;

    public function __construct(
        private EntityManager $entityManager,
        private QuantitySelectApplier $quantitySelectApplier,
    ) {}

    public function process(Entity $entity, Params $params): void
    {
        if ($entity->has($this->field)) {
            return;
        }

        $queryBuilder = SelectBuilder::create()
            ->from(InventoryNumber::ENTITY_TYPE)
            ->select([Attribute::ID, $this->field]);

        $applierParams = new ApplierParams(
            type: $this->type,
            isNumber: true,
        );

        $this->quantitySelectApplier->apply($queryBuilder, $applierParams);

        $number = $this->entityManager
            ->getRDBRepositoryByClass(InventoryNumber::class)
            ->clone($queryBuilder->build())
            ->where([Attribute::ID => $entity->getId()])
            ->findOne();

        $quantity = $number?->get($this->field) ?? 0.0;

        $entity->set($this->field, $quantity);
    }
}
