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

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Select\SelectBuilderFactory;
use Espo\Modules\Sales\Entities\Product;
use Espo\ORM\EntityManager;
use RuntimeException;

class AccessHelper
{
    public function __construct(
        private SelectBuilderFactory $selectBuilderFactory,
        private EntityManager $entityManager,
    ) {}

    /**
     * @param string[] $ids
     * @return string[]
     */
    public function filterIds(array $ids): array
    {
        try {
            $query = $this->selectBuilderFactory
                ->create()
                ->from(Product::ENTITY_TYPE)
                ->withAccessControlFilter()
                ->buildQueryBuilder()
                ->select(['id'])
                ->where(['id' => $ids])
                ->build();
        } catch (BadRequest|Forbidden $e) {
            throw new RuntimeException('', 0, $e);
        }

        $products = $this->entityManager
            ->getRDBRepositoryByClass(Product::class)
            ->clone($query)
            ->select(['id'])
            ->sth()
            ->find();

        return array_map(fn ($product) => $product->getId(), iterator_to_array($products));
    }
}
