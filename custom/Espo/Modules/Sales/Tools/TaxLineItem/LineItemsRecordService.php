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

namespace Espo\Modules\Sales\Tools\TaxLineItem;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Record\Collection;
use Espo\Core\Select\SearchParams;
use Espo\Core\Select\SelectBuilderFactory;
use Espo\Modules\Sales\Entities\TaxLineItem;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\ORM\EntityManager;

class LineItemsRecordService
{
    public function __construct(
        private EntityManager $entityManager,
        private SelectBuilderFactory $selectBuilderFactory,
    ) {}

    /**
     * @return Collection<TaxLineItem>
     * @throws BadRequest
     * @throws Forbidden
     */
    public function find(OrderEntity $order, SearchParams $searchParams): Collection
    {
        $query = $this->selectBuilderFactory
            ->create()
            ->from(TaxLineItem::ENTITY_TYPE)
            ->withSearchParams($searchParams)
            ->withComplexExpressionsForbidden()
            ->withWherePermissionCheck()
            ->buildQueryBuilder()
            ->where([
                TaxLineItem::FIELD_SOURCE . 'Id' => $order->getId(),
                TaxLineItem::FIELD_SOURCE . 'Type' => $order->getEntityType(),
            ])
            ->build();

        $repository = $this->entityManager->getRDBRepositoryByClass(TaxLineItem::class);

        $collection = $repository->clone($query)->find();
        $total = $repository->clone($query)->count();

        foreach ($collection as $entity) {
            $entity->loadParentNameField(TaxLineItem::FIELD_SOURCE);
            $entity->loadParentNameField(TaxLineItem::FIELD_ITEM);
        }

        return Collection::create($collection, $total);
    }
}
