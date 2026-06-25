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

namespace Espo\Modules\Sales\Classes\Select\InventoryNumber\AdditionalAppliers;

use Espo\Core\Select\Applier\AdditionalApplier;
use Espo\Core\Select\SearchParams;
use Espo\Core\Select\Where\Item;
use Espo\Modules\Sales\Tools\Product\Quantity\ApplierParams;
use Espo\Modules\Sales\Tools\Product\Quantity\QuantitySelectApplier;
use Espo\Modules\Sales\Tools\Sales\ConfigDataProvider;
use Espo\ORM\Query\SelectBuilder;

/**
 * @noinspection PhpUnused
 */
class Quantity implements AdditionalApplier
{
    public function __construct(
        private ConfigDataProvider $configDataProvider,
        private QuantitySelectApplier $quantitySelectApplier
    ) {}

    public function apply(SelectBuilder $queryBuilder, SearchParams $searchParams): void
    {
        [$parentType, $parentId] = $this->fetchParentPair($searchParams);
        $warehouseId = $this->fetchWarehouseId($searchParams);
        $warehouseFilterIds = $this->fetchWarehouseIdsFromFilter($searchParams);

        if ($this->toApply($searchParams, 'quantityReserved')) {
            $this->quantitySelectApplier->apply(
                $queryBuilder,
                new ApplierParams(
                    type: ApplierParams::TYPE_RESERVED,
                    parentType: $parentType,
                    parentId: $parentId,
                    warehouseId: $warehouseId,
                    isNumber: true,
                )
            );

            if ($warehouseFilterIds) {
                $this->quantitySelectApplier->apply(
                    $queryBuilder,
                    new ApplierParams(
                        type: ApplierParams::TYPE_RESERVED,
                        parentType: $parentType,
                        parentId: $parentId,
                        warehouseId: $warehouseId,
                        isNumber: true,
                        warehouseFilterIds: $warehouseFilterIds,
                    )
                );
            }
        }

        if ($this->toApply($searchParams, 'quantityOnHand')) {
            $this->quantitySelectApplier->apply(
                $queryBuilder,
                new ApplierParams(
                    type: ApplierParams::TYPE_ON_HAND,
                    parentType: $parentType,
                    parentId: $parentId,
                    warehouseId: $warehouseId,
                    isNumber: true,
                )
            );

            if ($warehouseFilterIds) {
                $this->quantitySelectApplier->apply(
                    $queryBuilder,
                    new ApplierParams(
                        type: ApplierParams::TYPE_ON_HAND,
                        parentType: $parentType,
                        parentId: $parentId,
                        warehouseId: $warehouseId,
                        isNumber: true,
                        warehouseFilterIds: $warehouseFilterIds,
                    )
                );
            }
        }
    }

    private function toApply(SearchParams $searchParams, string $field): bool
    {
        if (!$this->configDataProvider->isInventoryTransactionsEnabled()) {
            return false;
        }

        $select = $searchParams->getSelect() ?? [];

        if (in_array('*', $select) || in_array($field, $select)) {
            return true;
        }

        if ($searchParams->getOrderBy() === $field) {
            return true;
        }

        if (!$searchParams->getWhere()) {
            return false;
        }

        return $this->hasQuantityField($searchParams->getWhere(), $field);
    }

    private function hasQuantityField(Item $item, string $field): bool
    {
        if (
            $item->getType() === Item\Type::AND ||
            $item->getType() === Item\Type::OR ||
            $item->getType() === Item\Type::NOT
        ) {
            foreach ($item->getItemList() as $item) {
                if ($this->hasQuantityField($item, $field)) {
                    return true;
                }
            }

            return false;
        }

        return $item->getAttribute() === $field;
    }

    /**
     * @return array{?string, ?string}
     */
    private function fetchParentPair(SearchParams $searchParams): array
    {
        foreach ($searchParams->getSelect() ?? [] as $item) {
            if (!str_starts_with($item, 'quantity_')) {
                continue;
            }

            $array = explode('_', $item);

            if (count($array) !== 3) {
                return [null, null];
            }

            return [$array[1], $array[2]];
        }

        return [null, null];
    }

    private function fetchWarehouseId(SearchParams $searchParams): ?string
    {
        foreach ($searchParams->getSelect() ?? [] as $item) {
            if (!str_starts_with($item, 'warehouse_')) {
                continue;
            }

            $array = explode('_', $item);

            if (count($array) !== 2) {
                return null;
            }

            return $array[1];
        }

        return null;
    }

    /**
     * @return ?string[]
     */
    private function fetchWarehouseIdsFromFilter(SearchParams $searchParams): ?array
    {
        $where = $searchParams->getWhere();

        if (!$where || $where->getType() !== Item\Type::AND) {
            return null;
        }

        foreach ($where->getItemList() as $item) {
            if (
                $item->getAttribute() !== 'warehousesOnHandIds' ||
                $item->getType() !== 'arrayAnyOf'
            ) {
                continue;
            }

            $ids = $item->getValue();

            if (!is_array($ids)) {
                return null;
            }

            foreach ($ids as $id) {
                if (!is_string($id)) {
                    return null;
                }
            }

            return $ids;
        }

        return null;
    }
}
