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

namespace Espo\Modules\Sales\Classes\Select\Invoice\WhereItemConverters;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Select\Where\Item;
use Espo\Core\Select\Where\ItemConverter;
use Espo\ORM\Query\Part\Condition as Cond;
use Espo\ORM\Query\Part\Expression as Expr;
use Espo\ORM\Query\Part\WhereItem;
use Espo\ORM\Query\SelectBuilder;
use RuntimeException;

/**
 * @noinspection PhpUnused
 */
class OverdueDays implements ItemConverter
{
    public function convert(SelectBuilder $queryBuilder, Item $item): WhereItem
    {
        $sqAlias = $item->getAttribute() . 'Sq';

        $expr =
            Expr::coalesce(
                Expr::alias($sqAlias . '.days'),
                Expr::value(null)
            );

        $where = null;

        switch ($item->getType()) {
            case Item\Type::IS_NOT_NULL:
                $where = Expr::isNotNull($expr);

                break;

            case Item\Type::IS_NULL:
                $where = Expr::isNull($expr);

                break;

            case Item\Type::GREATER_THAN:
                $where = Cond::greater($expr, $item->getValue());

                break;

            case Item\Type::GREATER_THAN_OR_EQUALS:
                $where = Cond::greaterOrEqual($expr, $item->getValue());

                break;

            case Item\Type::LESS_THAN:
                $where = Cond::less($expr, $item->getValue());

                break;

            case Item\Type::LESS_THAN_OR_EQUALS:
                $where = Cond::lessOrEqual($expr, $item->getValue());

                break;

            case Item\Type::EQUALS:
                $where = Cond::equal($expr, $item->getValue());

                break;

            case Item\Type::NOT_EQUALS:
                $where = Cond::notEqual($expr, $item->getValue());

                break;

            case Item\Type::BETWEEN:
                if (!is_array($item->getValue())) {
                    throw new BadRequest();
                }

                [$v1, $v2] = $item->getValue();

                $where = Cond::and(
                    Cond::greaterOrEqual($expr, $v1),
                    Cond::lessOrEqual($expr, $v2)
                );

                break;
        }

        if (!$where) {
            throw new RuntimeException("Bad where item.");
        }

        return $where;
    }
}
