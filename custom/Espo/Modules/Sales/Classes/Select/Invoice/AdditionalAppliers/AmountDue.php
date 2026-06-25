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

namespace Espo\Modules\Sales\Classes\Select\Invoice\AdditionalAppliers;

use Espo\Core\Select\Applier\AdditionalApplier;
use Espo\Core\Select\SearchParams;
use Espo\Core\Select\Where\Item;
use Espo\Modules\Sales\Entities\CreditNote;
use Espo\Modules\Sales\Entities\PaymentAllocation;
use Espo\Modules\Sales\Entities\SupplierCredit;
use Espo\ORM\Name\Attribute;
use Espo\ORM\Query\Part\Condition;
use Espo\ORM\Query\Part\Expression as Expr;
use Espo\ORM\Query\Part\Join;
use Espo\ORM\Query\SelectBuilder;

/**
 * Used for the Invoice, CreditNote, SupplierBill, SupplierCredit.
 *
 * @noinspection PhpUnused
 */
class AmountDue implements AdditionalApplier
{
    private const FIELD = 'amountDue';

    public function __construct(
        private string $entityType,
    ) {}

    public function apply(SelectBuilder $queryBuilder, SearchParams $searchParams): void
    {
        if (!$this->toApply($searchParams)) {
            return;
        }

        if ($queryBuilder->build()->getSelect() === []) {
            $queryBuilder->select('*');
        }

        $sqAlias = self::FIELD . 'Sq';

        if ($queryBuilder->hasJoinAlias($sqAlias)) {
            return;
        }

        $expression =
            Expr::subtract(
                Expr::column('grandTotalAmount'),
                Expr::coalesce(
                    Expr::column($sqAlias . '.sum'),
                    Expr::value('0.0')
                )
            );

        if (
            $this->entityType === CreditNote::ENTITY_TYPE ||
            $this->entityType === SupplierCredit::ENTITY_TYPE
        ) {
            $expression = $this->applyForCredit($expression, $queryBuilder, $this->entityType);
        }

        $subQuery = SelectBuilder::create()
            ->from(PaymentAllocation::ENTITY_TYPE)
            ->select(
                Expr::sum(Expr::column('amount')),
                'sum'
            )
            ->select('targetId')
            ->select('targetType')
            ->group('targetId')
            ->group('targetType')
            ->build();

        $queryBuilder
            ->select($expression, self::FIELD)
            ->select(Expr::column('amountCurrency'), self::FIELD . 'Currency')
            ->leftJoin(
                Join::createWithSubQuery($subQuery, $sqAlias)
                    ->withConditions(
                        Condition::and(
                            Condition::equal(
                                Expr::alias($sqAlias . '.targetId'),
                                Expr::column(Attribute::ID)
                            ),
                            Condition::equal(
                                Expr::alias($sqAlias . '.targetType'),
                                $this->entityType
                            ),
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

    private function applyForCredit(Expr $expression, SelectBuilder $queryBuilder, string $entityType): Expr
    {
        $sqOutAlias = 'sqo';

        $idAttr = lcfirst($entityType) . 'Id';

        $expression = Expr::subtract(
            $expression,
            Expr::coalesce(
                Expr::column($sqOutAlias . '.sum'),
                Expr::value('0.0')
            )
        );

        $subQueryOut = SelectBuilder::create()
            ->from(PaymentAllocation::ENTITY_TYPE)
            ->select(
                Expr::sum(Expr::column('amount')),
                'sum'
            )
            ->select($idAttr)
            ->group($idAttr)
            ->build();

        $queryBuilder
            ->leftJoin(
                Join::createWithSubQuery($subQueryOut, $sqOutAlias)
                    ->withConditions(
                        Condition::and(
                            Condition::equal(
                                Expr::alias($sqOutAlias . '.' . $idAttr),
                                Expr::column(Attribute::ID)
                            ),
                        )
                    )
            );

        return $expression;
    }
}
