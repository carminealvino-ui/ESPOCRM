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

namespace Espo\Modules\Sales\Classes\Select\InventoryAdjustmentItem\AdditionalAppliers;

use Espo\Core\Select\Applier\AdditionalApplier;
use Espo\Core\Select\SearchParams;
use Espo\Modules\Sales\Entities\Warehouse as WarehouseEntity;
use Espo\ORM\Query\SelectBuilder;

class Warehouse implements AdditionalApplier
{
    public function apply(SelectBuilder $queryBuilder, SearchParams $searchParams): void
    {
        $select = $searchParams->getSelect() ?? [];

        $toApply =
            in_array('*', $select) ||
            in_array('warehouseId', $select) ||
            in_array('warehouseName', $select);

        if (!$toApply) {
            return;
        }

        $queryBuilder
            ->leftJoin('inventoryAdjustment')
            ->leftJoin(WarehouseEntity::ENTITY_TYPE, 'warehouse', [
                'warehouse.id:' => 'inventoryAdjustment.warehouseId',
            ])
            ->select('inventoryAdjustment.warehouseId', 'warehouseId')
            ->select('warehouse.name', 'warehouseName');
    }
}
