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

namespace Espo\Modules\Sales\Select\TaxAllocationItem\Order;

use Espo\Core\Name\Field;
use Espo\Core\Select\Order\Item;
use Espo\Core\Select\Order\Orderer;
use Espo\Modules\Sales\Entities\TaxAllocationItem;
use Espo\ORM\Query\Part\Expression;
use Espo\ORM\Query\SelectBuilder;

/**
 * @noinspection PhpUnused
 */
class GroupedOrder implements Orderer
{
    public function apply(SelectBuilder $queryBuilder, Item $item): void
    {
        $queryBuilder
            ->leftJoin(TaxAllocationItem::FIELD_ALLOCATION)
            ->order(
                Expression::column(TaxAllocationItem::FIELD_ALLOCATION . '.' . Field::CREATED_AT),
                $item->getOrder()
            )
            ->order(
                Expression::column(TaxAllocationItem::FIELD_ORDER),
                $item->getOrder()
            );
    }
}
