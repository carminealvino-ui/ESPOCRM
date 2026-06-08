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

namespace Espo\Modules\Sales\Tools\Product\Quantity;

use Espo\Modules\Sales\Entities\DeliveryOrder;
use Espo\Modules\Sales\Entities\InventoryTransaction;
use Espo\Modules\Sales\Entities\Product;
use Espo\Modules\Sales\Entities\SalesOrder;
use Espo\Modules\Sales\Entities\TransferOrder;
use Espo\Modules\Sales\Entities\Warehouse;
use Espo\Modules\Sales\Tools\Sales\ConfigDataProvider;
use Espo\ORM\EntityManager;
use Espo\ORM\Query\Part\Condition;
use Espo\ORM\Query\Part\Expression as Expr;
use Espo\ORM\Query\Part\Join;
use Espo\ORM\Query\SelectBuilder;
use RuntimeException;

class QuantitySelectApplier
{
    /** @var ?string[] */
    private ?array $excludeWarehouseIds = null;

    public function __construct(
        private ConfigDataProvider $configDataProvider,
        private EntityManager $entityManager
    ) {}

    public function apply(SelectBuilder $queryBuilder, ApplierParams $params): void
    {
        $negate = false;

        [$alias, $sqAlias] = match ($params->getType()) {
            ApplierParams::TYPE_RESERVED => ['quantityReserved', 'quantityReservedSq'],
            ApplierParams::TYPE_SOFT_RESERVED => ['quantitySoftReserved', 'quantitySoftReservedSq'],
            ApplierParams::TYPE_ON_HAND => ['quantityOnHand', 'quantityOnHandSq'],
            default => ['quantity', 'quantitySq'],
        };

        if ($params->getWarehouseFilterIds()) {
            if ($params->getType() === ApplierParams::TYPE_SOFT_RESERVED) {
                throw new RuntimeException("Not supported warehouse quantity type.");
            }

            [$alias, $sqAlias] = match ($params->getType()) {
                ApplierParams::TYPE_RESERVED => ['quantityWarehouseReserved', 'quantityWarehouseReservedSq'],
                ApplierParams::TYPE_ON_HAND => ['quantityWarehouseOnHand', 'quantityWarehouseOnHandSq'],
                default => ['quantityWarehouse', 'quantityWarehouseSq'],
            };
        }

        if ($queryBuilder->hasJoinAlias($sqAlias)) {
            // Already applied.
            return;
        }

        $sq1Alias = $sqAlias . '1';

        if (
            in_array($params->getType(), [
                ApplierParams::TYPE_RESERVED,
                ApplierParams::TYPE_SOFT_RESERVED,
            ])
        ) {
            $negate = true;
        }

        $sumExpression = Expr::sum(Expr::column('quantity'));

        if ($negate) {
            $sumExpression = Expr::multiply($sumExpression, -1.0);
        }

        $groupBy = $params->isNumber() ?
            'inventoryNumberId' :
            'productId';

        $subQueryBuilder = SelectBuilder::create()
            ->from(InventoryTransaction::ENTITY_TYPE)
            ->select($sumExpression, 'sum')
            // 'prodid' is used to avoid conversion to underscore.
            ->select($groupBy, 'prodid')
            ->group($groupBy);

        if ($params->getWarehouseId()) {
            $subQueryBuilder->where(['warehouseId' => $params->getWarehouseId()]);
        } else if ($params->getWarehouseFilterIds()) {
            $subQueryBuilder->where(['warehouseId' => $params->getWarehouseFilterIds()]);
        }

        if (
            $params->excludeSoftReserve() ||
            $params->getType() === ApplierParams::TYPE_ON_HAND
        ) {
            $subQueryBuilder->where(['type!=' => InventoryTransaction::TYPE_SOFT_RESERVE]);
        }
        else if ($params->getType() === ApplierParams::TYPE_RESERVED) {
            $subQueryBuilder->where(['type' => InventoryTransaction::TYPE_RESERVE]);
        }
        else if ($params->getType() === ApplierParams::TYPE_SOFT_RESERVED) {
            $subQueryBuilder->where(['type' => InventoryTransaction::TYPE_SOFT_RESERVE]);
        }

        $parentId = $params->getParentId();
        $parentType = $params->getParentType();

        if (
            $parentId &&
            in_array($parentType, [
                SalesOrder::ENTITY_TYPE,
                DeliveryOrder::ENTITY_TYPE,
                TransferOrder::ENTITY_TYPE,
            ])
        ) {
            $subQueryBuilder->where([
                'OR' => [
                    'parentId!=' => $parentId,
                    'parentType!=' => $parentType,
                    'parentId' => null,
                ]
            ]);
        }

        $this->applyExcludeWarehouses($subQueryBuilder, $params);

        $subQuery = $subQueryBuilder->build();

        $subQuery1 = null;

        if (!$params->isNumber()) {
            $subQuery1 = SelectBuilder::create()
                ->clone($subQuery)
                ->select([])
                ->select($sumExpression, 'sum')
                ->select('p.templateId', 'tid')
                ->group(['p.templateId'])
                ->join(Product::ENTITY_TYPE, 'p', ['p.id:' => 'productId'])
                ->build();
        }

        $selectExpr =
            Expr::coalesce(
                Expr::column($sqAlias . '.sum'),
                Expr::value('0.0')
            );

        if (!$params->isNumber()) {
            $selectExpr =
                Expr::if(
                    Expr::equal(Expr::column('type'), Product::TYPE_TEMPLATE),
                    Expr::ifNull(Expr::column($sq1Alias . '.sum'), 0.0),
                    Expr::ifNull(Expr::column($sqAlias . '.sum'), 0.0)
                );

            $selectExpr = Expr::if(Expr::column('isInventory'), $selectExpr, null);
        }

        if ($queryBuilder->build()->getSelect() === []) {
            $queryBuilder->select('*');
        }

        $queryBuilder
            ->select($selectExpr, $alias)
            ->leftJoin(
                Join::createWithSubQuery($subQuery, $sqAlias)
                    ->withConditions(
                        Condition::equal(
                            Expr::column($sqAlias . '.prodid'),
                            Expr::column('id')
                        )
                    )
            );

        if ($subQuery1) {
            $queryBuilder
                ->leftJoin(
                    Join::createWithSubQuery($subQuery1, $sq1Alias)
                        ->withConditions(
                            Condition::equal(
                                Expr::column($sq1Alias . '.tid'),
                                Expr::column('id')
                            )
                        )
                );
        }
    }

    private function applyExcludeWarehouses(SelectBuilder $subQueryBuilder, ApplierParams $params): void
    {
        if (
            !$this->configDataProvider->isWarehousesEnabled() ||
            $params->getWarehouseId()
        ) {
            return;
        }

        $subQueryBuilder->where([
            'OR' => [
                'warehouseId!=' => $this->getExcludeWarehouseIds(),
                'warehouseId' => null,
            ]
        ]);
    }

    /**
     * @return string[]
     */
    private function getExcludeWarehouseIds(): array
    {
        if ($this->excludeWarehouseIds !== null) {
            return $this->excludeWarehouseIds;
        }

        $warehouses = $this->entityManager
            ->getRDBRepositoryByClass(Warehouse::class)
            ->select('id')
            ->where(['isAvailableForStock' => false])
            ->find();

        $ids = array_map(
            fn ($warehouse) => $warehouse->getId(),
            iterator_to_array($warehouses)
        );

        $this->excludeWarehouseIds = $ids;

        return $ids;
    }
}
