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

use Espo\Core\Select\Where\Item;
use Espo\Core\Select\Where\ItemConverter;
use Espo\Modules\Sales\Entities\Product;
use Espo\Modules\Sales\Entities\SupplierProductPrice;
use Espo\ORM\Query\Part\Condition as Cond;
use Espo\ORM\Query\Part\Expression as Expr;
use Espo\ORM\Query\Part\Join;
use Espo\ORM\Query\Part\WhereItem as WhereClauseItem;
use Espo\ORM\Query\SelectBuilder;

/**
 * @noinspection PhpUnused
 */
class SuppliersLinkedWith implements ItemConverter
{
    public function convert(SelectBuilder $queryBuilder, Item $item): WhereClauseItem
    {
        return Cond::or(
            Cond::in(
                Expr::column('id'),
                SelectBuilder::create()
                    ->select('productId')
                    ->from(SupplierProductPrice::ENTITY_TYPE)
                    ->where([
                        'supplierId' => $item->getValue(),
                        'status' => SupplierProductPrice::STATUS_ACTIVE,
                    ])
                    ->build()
            ),
            Cond::in(
                Expr::column('id'),
                SelectBuilder::create()
                    ->select('template.id')
                    ->from(SupplierProductPrice::ENTITY_TYPE)
                    ->join(
                        Join::createWithRelationTarget('product')
                    )
                    ->join(
                        Join::createWithTableTarget(Product::ENTITY_TYPE, 'template')
                            ->withConditions(
                                Cond::and(
                                    Cond::equal(
                                        Expr::column('template.id'),
                                        Expr::column('product.templateId'),
                                    ),
                                    Cond::equal(
                                        Expr::column('template.deleted'),
                                        false,
                                    ),
                                )
                            )
                    )
                    ->where([
                        'supplierId' => $item->getValue(),
                        'status' => SupplierProductPrice::STATUS_ACTIVE,
                    ])
                    ->build()
            ),
            Cond::in(
                Expr::column('id'),
                SelectBuilder::create()
                    ->select('product.id')
                    ->from(SupplierProductPrice::ENTITY_TYPE)
                    ->join(
                        Join::createWithRelationTarget('product', 'template')
                    )
                    ->join(
                        Join::createWithTableTarget(Product::ENTITY_TYPE, 'product')
                            ->withConditions(
                                Cond::and(
                                    Cond::equal(
                                        Expr::column('template.id'),
                                        Expr::column('product.templateId'),
                                    ),
                                    Cond::equal(
                                        Expr::column('product.deleted'),
                                        false,
                                    ),
                                )
                            )
                    )
                    ->where([
                        'supplierId' => $item->getValue(),
                        'status' => SupplierProductPrice::STATUS_ACTIVE,
                    ])
                    ->build()
            ),
        );
    }
}
