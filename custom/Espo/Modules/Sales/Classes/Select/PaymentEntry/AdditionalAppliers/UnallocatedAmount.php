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

namespace Espo\Modules\Sales\Classes\Select\PaymentEntry\AdditionalAppliers;

use Espo\Core\Select\Applier\AdditionalApplier;
use Espo\Core\Select\SearchParams;
use Espo\Core\Select\Where\Item;
use Espo\Modules\Sales\Entities\PaymentAllocation;
use Espo\Modules\Sales\Entities\PaymentEntry;
use Espo\Modules\Sales\Entities\WriteOffEntry;
use Espo\ORM\Query\Part\Condition;
use Espo\ORM\Query\Part\Expression as Expr;
use Espo\ORM\Query\Part\Join;
use Espo\ORM\Query\SelectBuilder;

/**
 * @noinspection PhpUnused
 *
 * Used also by WriteOffEntry.
 */
class UnallocatedAmount implements AdditionalApplier
{
    private const FIELD = 'unallocatedAmount';

    public function __construct(
        private string $entityType,
    ) {}

    public function apply(SelectBuilder $queryBuilder, SearchParams $searchParams): void
    {
        if (!$this->toApply($searchParams)) {
            return;
        }

        $sqAlias = 'unallocatedAmount' . 'Sq';

        if ($queryBuilder->hasJoinAlias($sqAlias)) {
            return;
        }

        if ($queryBuilder->build()->getSelect() === []) {
            $queryBuilder->select('*');
        }

        $idAttr = PaymentAllocation::ATTR_PAYMENT_ENTRY_ID;

        if ($this->entityType === WriteOffEntry::ENTITY_TYPE) {
            $idAttr = PaymentAllocation::ATTR_WRITE_OFF_ENTRY_ID;
        }

        $subQuery = SelectBuilder::create()
            ->from(PaymentAllocation::ENTITY_TYPE)
            ->select(
                Expr::sum(
                    Expr::column('amount')
                ),
                'sum'
            )
            ->select($idAttr)
            ->group($idAttr)
            ->build();



        $expression =
            Expr::subtract(
                Expr::column('amount'),
                Expr::coalesce(
                    Expr::column($sqAlias . '.sum'),
                    Expr::value('0.0')
                )
            );

        $queryBuilder
            ->select($expression, self::FIELD)
            ->select(Expr::column('amountCurrency'), self::FIELD . 'Currency')
            ->leftJoin(
                Join::createWithSubQuery($subQuery, $sqAlias)
                    ->withConditions(
                        Condition::equal(
                            Expr::alias($sqAlias . '.' . $idAttr),
                            Expr::column('id')
                        )
                    )
            );
    }

    private function toApply(SearchParams $searchParams): bool
    {
        $select = $searchParams->getSelect() ?? [];

        if (in_array('*', $select) || in_array(self::FIELD, $select)) {
            return true;
        }

        if (!$searchParams->getWhere()) {
            return false;
        }

        return $this->hasField($searchParams->getWhere());
    }

    private function hasField(Item $item): bool
    {
        if (
            $item->getType() === Item\Type::AND ||
            $item->getType() === Item\Type::OR ||
            $item->getType() === Item\Type::NOT
        ) {
            foreach ($item->getItemList() as $item) {
                if ($this->hasField($item)) {
                    return true;
                }
            }

            return false;
        }

        return $item->getAttribute() === self::FIELD;
    }
}
