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

use Espo\Core\Acl;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Record\Collection;
use Espo\Core\Select\SearchParams;
use Espo\Core\Select\SelectBuilderFactory;
use Espo\Modules\Sales\Entities\CreditNoteItem;
use Espo\Modules\Sales\Entities\DeliveryOrderItem;
use Espo\Modules\Sales\Entities\InventoryAdjustment;
use Espo\Modules\Sales\Entities\InventoryAdjustmentItem;
use Espo\Modules\Sales\Entities\InvoiceItem;
use Espo\Modules\Sales\Entities\Product;
use Espo\Modules\Sales\Entities\PurchaseOrderItem;
use Espo\Modules\Sales\Entities\QuoteItem;
use Espo\Modules\Sales\Entities\ReceiptOrderItem;
use Espo\Modules\Sales\Entities\ReturnOrderItem;
use Espo\Modules\Sales\Entities\SalesOrderItem;
use Espo\Modules\Sales\Entities\SupplierBillItem;
use Espo\Modules\Sales\Entities\SupplierCreditItem;
use Espo\Modules\Sales\Entities\TransferOrderItem;
use Espo\Modules\Sales\Tools\Sales\OrderEntityUtil;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Core\Utils\Metadata;
use Espo\ORM\Query\Part\Expression as Expr;
use Espo\ORM\Query\Part\Order;
use Espo\ORM\Query\SelectBuilder;
use Espo\ORM\Query\UnionBuilder;

class OrderItemsService
{
    /** @var string[] */
    private array $entityTypes = [
        QuoteItem::ENTITY_TYPE,
        SalesOrderItem::ENTITY_TYPE,
        InvoiceItem::ENTITY_TYPE,
        CreditNoteItem::ENTITY_TYPE,
        DeliveryOrderItem::ENTITY_TYPE,
        ReturnOrderItem::ENTITY_TYPE,
        PurchaseOrderItem::ENTITY_TYPE,
        ReceiptOrderItem::ENTITY_TYPE,
        TransferOrderItem::ENTITY_TYPE,
        InventoryAdjustmentItem::ENTITY_TYPE,
        SupplierBillItem::ENTITY_TYPE,
        SupplierCreditItem::ENTITY_TYPE,
    ];

    private const FILTER_ACTUAL = 'actual';
    private const FILTER_COMPLETED = 'completed';

    public function __construct(
        private Acl $acl,
        private SelectBuilderFactory $selectBuilderFactory,
        private EntityManager $entityManager,
        private Metadata $metadata,
    ) {}

    /**
     * @param Product $product
     * @param SearchParams $searchParams
     * @return Collection<Entity>
     * @throws BadRequest
     * @throws Forbidden
     */
    public function find(Product $product, SearchParams $searchParams): Collection
    {
        $unionBuilder = UnionBuilder::create();

        $offset = $searchParams->getOffset() ?? 0;
        $maxSize = $searchParams->getMaxSize() ?? 0;

        $entityTypes = $this->getEntityTypes();

        if ($entityTypes === []) {
            $collection = $this->entityManager->getCollectionFactory()->create();

            return Collection::create($collection, $maxSize);
        }

        foreach ($entityTypes as $entityType) {
            $parentEntityType = OrderEntityUtil::getItemParentEntityType($entityType);
            $parentLink = OrderEntityUtil::getItemParentField($entityType);

            $itemBuilder = $this->selectBuilderFactory
                ->create()
                ->from($entityType)
                ->withAccessControlFilter()
                ->buildQueryBuilder()
                ->order([]);

            $itemBuilder
                ->select([
                    'id',
                    ["$parentLink.id", 'parentId'],
                    ["$parentLink.status", 'parentStatus'],
                    ["$parentLink.number", 'name'],
                    'quantity',
                    'createdAt',
                ])
                ->select(Expr::value($entityType), 'entityType')
                ->select(Expr::value($parentEntityType), 'parentType')
                ->join($parentLink)
                ->limit(0, $offset + $maxSize + 1)
                ->order('createdAt', Order::DESC)
                ->order('id', Order::DESC);

            $this->applyProductTemplate($itemBuilder, $product);
            $this->applyStatusFilter($parentEntityType, $parentLink, $itemBuilder, $searchParams);

            $unionBuilder->query($itemBuilder->build());
        }

        $unionQuery = $unionBuilder
            ->order('createdAt', Order::DESC)
            ->order('id', Order::DESC)
            ->limit($searchParams->getOffset(), $maxSize + 1)
            ->build();

        $sth = $this->entityManager->getQueryExecutor()->execute($unionQuery);

        $collection = $this->entityManager->getCollectionFactory()->create();

        while ($row = $sth->fetch()) {
            /** @var string $entityType */
            $entityType = $row['entityType'];
            /** @var string $entityType */
            $parentType = $row['parentType'];

            $entity = $this->entityManager->getNewEntity($entityType);
            $entity->set($row);
            $entity->set(lcfirst($parentType) . 'Status', $row['parentStatus']);
            $entity->set(lcfirst($parentType) . 'Id', $row['parentId']);
            $entity->setAsFetched();

            $collection[] = $entity;
        }

        return Collection::createNoCount($collection, $maxSize);
    }

    /**
     * @return string[]
     */
    private function getEntityTypes(): array
    {
        return array_values(array_filter(
            $this->entityTypes,
            fn ($entityType) => $this->acl->checkScope($entityType)
        ));
    }

    /**
     * @throws BadRequest
     */
    private function applyStatusFilter(
        string $entityType,
        string $parentLink,
        SelectBuilder $builder,
        SearchParams $searchParams
    ): void {

        $filter = $searchParams->getPrimaryFilter();

        if (!$filter) {
            return;
        }

        if ($filter === self::FILTER_ACTUAL) {
            $this->applyActualFilter($entityType, $parentLink,$builder);

            return;
        }

        if ($filter === self::FILTER_COMPLETED) {
            $this->applyCompletedFilter($entityType, $parentLink, $builder);

            return;
        }

        throw new BadRequest("Unknown filter '$filter'.");
    }

    private function applyActualFilter(string $entityType, string $parentLink, SelectBuilder $builder): void
    {
        $notActualStatusList = $this->metadata->get("entityDefs.$entityType.fields.status.notActualOptions") ?? [];

        $builder->where(["$parentLink.status!=" => $notActualStatusList]);
    }

    private function applyCompletedFilter(string $entityType, string $parentLink, SelectBuilder $builder): void
    {
        if ($entityType === InventoryAdjustment::ENTITY_TYPE) {
            $builder->where(["$parentLink.status" => InventoryAdjustment::STATUS_COMPLETED]);

            return;
        }

        $statusList = $this->metadata->get("scopes.$entityType.doneStatusList") ?? [];

        $builder->where(["$parentLink.status" => $statusList]);
    }

    private function applyProductTemplate(SelectBuilder $builder, Product $product): void
    {
        if ($product->getType() !== Product::TYPE_TEMPLATE) {
            $builder->where(['productId' => $product->getId()]);

            return;
        }

        $builder
            ->join('product')
            ->where(['product.templateId' => $product->getId()]);
    }
}
