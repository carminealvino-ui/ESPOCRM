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

namespace Espo\Modules\Sales\Classes\Select\InventoryNumber\WhereItemConverters;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Select\Where\Item;
use Espo\Core\Select\Where\ItemConverter;
use Espo\Modules\Sales\Entities\InventoryTransaction;
use Espo\Modules\Sales\Tools\Sales\ConfigDataProvider;
use Espo\ORM\Query\Part\Condition;
use Espo\ORM\Query\Part\Expression as Expr;
use Espo\ORM\Query\Part\Join;
use Espo\ORM\Query\Part\Where\OrGroup;
use Espo\ORM\Query\Part\WhereItem as WhereClauseItem;
use Espo\ORM\Query\SelectBuilder;
use Espo\ORM\Query\SelectBuilder as QueryBuilder;
use RuntimeException;

/**
 * @noinspection PhpUnused
 */
class WarehousesOnHand implements ItemConverter
{
    public function __construct(
        private ConfigDataProvider $configDataProvider
    ) {}

    /**
     * @throws BadRequest
     */
    public function convert(QueryBuilder $queryBuilder, Item $item): WhereClauseItem
    {
        if (!$this->toApply()) {
            return Expr::isNull(Expr::column('id'));
        }

        if ($item->getType() !== Item\Type::ARRAY_ANY_OF) {
            throw new RuntimeException("Bad where item.");
        }

        $warehouseIds = $item->getValue();

        if (!is_array($warehouseIds)) {
            throw new BadRequest("No IDs in value.");
        }

        foreach ($warehouseIds as $id) {
            if (!is_string($id)) {
                throw new BadRequest();
            }
        }

        $sqAliases = [];

        foreach ($warehouseIds as $i => $warehouseId) {
            $subQuery = SelectBuilder::create()
                ->from(InventoryTransaction::ENTITY_TYPE)
                ->select(Expr::sum(Expr::column('quantity')), 'sum')
                // 'nid' is used to avoid conversion to underscore.
                ->select('inventoryNumberId', 'nid')
                ->group('inventoryNumberId')
                ->where(['warehouseId' => $warehouseId])
                ->where(['type!=' => InventoryTransaction::TYPE_SOFT_RESERVE])
                ->build();

            $sqAlias = 'warehouseOnHandSq' . $i;

            $queryBuilder
                ->leftJoin(
                    Join::createWithSubQuery($subQuery, $sqAlias)
                        ->withConditions(
                            Condition::equal(
                                Expr::column($sqAlias . '.nid'),
                                Expr::column('id')
                            )
                        )
                );

            $sqAliases[] = $sqAlias;
        }

        $orBuilder = OrGroup::createBuilder();

        foreach ($sqAliases as $sqAlias) {
            $expr =
                Expr::coalesce(
                    Expr::column($sqAlias . '.sum'),
                    Expr::value('0.0')
                );

            $orBuilder->add(
                Condition::greater($expr, 0.0)
            );
        }

        return $orBuilder->build();
    }

    private function toApply(): bool
    {
        if (!$this->configDataProvider->isInventoryTransactionsEnabled()) {
            return false;
        }

        return true;
    }
}
