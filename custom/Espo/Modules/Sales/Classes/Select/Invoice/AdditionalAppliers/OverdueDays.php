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
use Espo\Core\Utils\DateTime;
use Espo\Modules\Sales\Entities\PaymentInstallment;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\ORM\Name\Attribute;
use Espo\ORM\Query\Part\Condition;
use Espo\ORM\Query\Part\Expression as Expr;
use Espo\ORM\Query\Part\Join;
use Espo\ORM\Query\SelectBuilder;

/**
 * @noinspection PhpUnused
 */
class OverdueDays implements AdditionalApplier
{
    private const FIELD = 'overdueDays';

    public function __construct(
        private string $entityType,
        private DateTime $dateTime,
    ) {}

    public function apply(SelectBuilder $queryBuilder, SearchParams $searchParams): void
    {
        if (!$this->toApply($searchParams)) {
            return;
        }

        if ($queryBuilder->build()->getSelect() === []) {
            $queryBuilder->select('*');
        }

        $today = $this->dateTime->getToday()->toString();

        $sqAlias = self::FIELD . 'Sq';

        if ($queryBuilder->hasJoinAlias($sqAlias)) {
            return;
        }

        $subQuery = SelectBuilder::create()
            ->from(PaymentInstallment::ENTITY_TYPE)
            ->select(
                Expr::max(
                    Expr::create("TIMESTAMPDIFF_DAY:(date, '$today')"),
                ),
                'days'
            )
            ->select(PaymentInstallment::FIELD_SOURCE . 'Id', 'sourceId')
            ->select(PaymentInstallment::FIELD_SOURCE . 'Type', 'sourceType')
            ->where([
                PaymentInstallment::FIELD_STATUS . '!=' => PaymentInstallment::STATUS_SETTLED,
            ])
            ->group(PaymentInstallment::FIELD_SOURCE . 'Id')
            ->group(PaymentInstallment::FIELD_SOURCE . 'Type')
            ->build();

        $queryBuilder
            ->select(
                Expr::if(
                    Expr::or(
                        Expr::equal(Expr::column(OrderEntity::FIELD_IS_ISSUED), false),
                        Expr::equal(Expr::column(OrderEntity::FIELD_IS_NOT_ACTUAL), true),
                    ),
                    null,
                    Expr::alias($sqAlias . '.days'
                )
            ), self::FIELD)
            ->leftJoin(
                Join::createWithSubQuery($subQuery, $sqAlias)
                    ->withConditions(
                        Condition::and(
                            Condition::equal(
                                Expr::alias($sqAlias . '.sourceId'),
                                Expr::column(Attribute::ID)
                            ),
                            Condition::equal(
                                Expr::alias($sqAlias . '.sourceType'),
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
}
