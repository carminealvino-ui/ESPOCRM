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
use Espo\Core\Record\Collection as RecordCollection;
use Espo\Core\Select\SearchParams;
use Espo\Core\Select\SelectBuilderFactory;
use Espo\Modules\Sales\Entities\InventoryNumber;
use Espo\Modules\Sales\Entities\Product;
use Espo\ORM\Collection;
use Espo\ORM\EntityManager;

class VariantInventoryNumbersService
{
    public function __construct(
        private SelectBuilderFactory $selectBuilderFactory,
        private EntityManager $entityManager,
    ) {}

    /**
     * @return RecordCollection<InventoryNumber>
     * @throws BadRequest
     * @throws Forbidden
     */
    public function findTemplateInventoryNumbers(Product $product, SearchParams $searchParams): RecordCollection
    {
        if ($product->getType() !== Product::TYPE_TEMPLATE) {
            throw new Forbidden("Not a template product.");
        }

        $query = $this->selectBuilderFactory
            ->create()
            ->from(InventoryNumber::ENTITY_TYPE)
            ->withStrictAccessControl()
            ->withSearchParams($searchParams)
            ->buildQueryBuilder()
            ->select('productId')
            ->leftJoin('product')
            ->where(['product.templateId' => $product->getId()])
            ->build();

        $repository = $this->entityManager->getRDBRepositoryByClass(InventoryNumber::class);

        $collection = $repository->clone($query)->find();

        $this->loadVariantValues($collection);

        return RecordCollection::create(
            $collection,
            $repository->clone($query)->count()
        );
    }

    /**
     * @param Collection<InventoryNumber> $collection
     */
    private function loadVariantValues(Collection $collection): void
    {
        foreach ($collection as $entity) {
            $this->loadVariantValuesForEntity($entity);
        }
    }

    private function loadVariantValuesForEntity(InventoryNumber $entity): void
    {
        $product = $this->entityManager
            ->getRDBRepositoryByClass(Product::class)
            ->where(['id' => $entity->getProduct()->getId()])
            ->select('id')
            ->findOne();

        if (!$product) {
            return;
        }

        $optionIds = $product->getLinkMultipleIdList('variantAttributeOptions');

        /** @var array<string, string> $names */
        $names = get_object_vars($product->get('variantAttributeOptionsNames') ?? (object) []);

        $list = array_map(
            fn ($id) => $names[$id] ?? $id,
            $optionIds
        );

        $name = implode(' · ', $list);

        $entity->set('productName', $name);
    }
}
