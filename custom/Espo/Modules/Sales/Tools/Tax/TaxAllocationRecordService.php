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

namespace Espo\Modules\Sales\Tools\Tax;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Record\Collection;
use Espo\Core\Select\SearchParams;
use Espo\Core\Select\SelectBuilderFactory;
use Espo\Modules\Sales\Entities\PaymentEntry;
use Espo\Modules\Sales\Entities\TaxAllocationItem;
use Espo\ORM\EntityManager;

class TaxAllocationRecordService
{
    public function __construct(
        private EntityManager $entityManager,
        private SelectBuilderFactory $selectBuilderFactory,
    ) {}

    /**
     * @return Collection<TaxAllocationItem>
     * @throws BadRequest
     * @throws Forbidden
     */
    public function find(PaymentEntry $entry, SearchParams $searchParams): Collection
    {
        $query = $this->selectBuilderFactory
            ->create()
            ->from(TaxAllocationItem::ENTITY_TYPE)
            ->withSearchParams($searchParams)
            ->withComplexExpressionsForbidden()
            ->withWherePermissionCheck()
            ->buildQueryBuilder()
            ->where([
                TaxAllocationItem::FIELD_PAYMENT_ENTRY . 'Id' => $entry->getId(),
            ])
            ->build();

        $repository = $this->entityManager->getRDBRepositoryByClass(TaxAllocationItem::class);

        $collection = $repository->clone($query)->find();
        $total = $repository->clone($query)->count();

        foreach ($collection as $entity) {
            $entity->loadParentNameField(TaxAllocationItem::FIELD_SOURCE);
            $entity->loadParentNameField(TaxAllocationItem::FIELD_ITEM);
        }

        return Collection::create($collection, $total);
    }
}
