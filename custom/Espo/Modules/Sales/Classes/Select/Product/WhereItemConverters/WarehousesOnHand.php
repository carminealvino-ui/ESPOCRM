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

namespace Espo\Modules\Sales\Classes\Select\Product\WhereItemConverters;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Select\Where\Item;
use Espo\Core\Select\Where\ItemConverter;
use Espo\Modules\Sales\Entities\InventoryTransaction;
use Espo\Modules\Sales\Entities\Product;
use Espo\Modules\Sales\Tools\Sales\ConfigDataProvider;
use Espo\ORM\Query\Part\Condition;
use Espo\ORM\Query\Part\Expression as Expr;
use Espo\ORM\Query\Part\Join;
use Espo\ORM\Query\Part\Where\AndGroup;
use Espo\ORM\Query\Part\Where\OrGroup;
use Espo\ORM\Query\Part\WhereItem as WhereClauseItem;
use Espo\ORM\Query\SelectBuilder;
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
    public function convert(SelectBuilder $queryBuilder, Item $item): WhereClauseItem
    {
        if (!$this->toApply()) {
            return Expr::isNull(Expr::column('id'));
        }

        if (
            $item->getType() !== Item\Type::ARRAY_ANY_OF &&
            $item->getType() !== Item\Type::ARRAY_ALL_OF &&
            $item->getType() !== Item\Type::ARRAY_NONE_OF
        ) {
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
            $subQuery1 = SelectBuilder::create()
                ->from(InventoryTransaction::ENTITY_TYPE)
                ->select(Expr::sum(Expr::column('quantity')), 'sum')
                // 'prodid' is used to avoid conversion to underscore.
                ->select('productId', 'prodid')
                ->group('productId')
                ->where(['warehouseId' => $warehouseId])
                ->where(['type!=' => InventoryTransaction::TYPE_SOFT_RESERVE])
                ->build();

            $subQuery2 = SelectBuilder::create()
                ->clone($subQuery1)
                ->select([])
                ->select(Expr::sum(Expr::column('quantity')), 'sum')
                ->select('p.templateId', 'tid')
                ->group(['p.templateId'])
                ->join(Product::ENTITY_TYPE, 'p', ['p.id:' => 'productId'])
                ->join(Product::ENTITY_TYPE, 'template', ['template.id:' => 'p.templateId'])
                ->build();

            $sq1Alias = 'warehouseOnHand1Sq' . $i;
            $sq2Alias = 'warehouseOnHand2Sq' . $i;

            $queryBuilder
                ->leftJoin(
                    Join::createWithSubQuery($subQuery1, $sq1Alias)
                        ->withConditions(
                            Condition::equal(
                                Expr::column($sq1Alias . '.prodid'),
                                Expr::column('id')
                            )
                        )
                )
                ->leftJoin(
                    Join::createWithSubQuery($subQuery2, $sq2Alias)
                        ->withConditions(
                            Condition::equal(
                                Expr::column($sq2Alias . '.tid'),
                                Expr::column('id')
                            )
                        )
                );

            $sqAliases[] = [$sq1Alias, $sq2Alias];
        }

        if ($item->getType() === Item\Type::ARRAY_ANY_OF) {
            $orBuilder = OrGroup::createBuilder();

            foreach ($sqAliases as $sqAlias) {
                $expr = $this->createItemExpr($sqAlias);

                $orBuilder->add(
                    Condition::greater($expr, 0.0)
                );
            }

            return $orBuilder->build();
        }

        $andBuilder = AndGroup::createBuilder();

        if ($item->getType() === Item\Type::ARRAY_ALL_OF) {
            foreach ($sqAliases as $sqAlias) {
                $expr = $this->createItemExpr($sqAlias);

                $andBuilder->add(
                    Condition::greater($expr, 0.0)
                );
            }

            return $andBuilder->build();
        }

        foreach ($sqAliases as $sqAlias) {
            $expr = $this->createItemExpr($sqAlias);

            $andBuilder->add(
                Condition::lessOrEqual($expr, 0.0)
            );
        }

        return $andBuilder->build();
    }

    private function toApply(): bool
    {
        if (!$this->configDataProvider->isInventoryTransactionsEnabled()) {
            return false;
        }

        return true;
    }

    /**
     * @param array{string, string} $aliasPair
     */
    private function createItemExpr(array $aliasPair): Expr
    {
        return Expr::if(
            Expr::equal(Expr::column('type'), Product::TYPE_TEMPLATE),
            Expr::ifNull(Expr::column($aliasPair[1] . '.sum'), 0.0),
            Expr::ifNull(Expr::column($aliasPair[0] . '.sum'), 0.0)
        );
    }
}
