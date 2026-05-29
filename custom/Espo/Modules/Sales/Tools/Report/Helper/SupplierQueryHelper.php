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

namespace Espo\Modules\Sales\Tools\Report\Helper;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Field\Date;
use Espo\Core\Select\SelectBuilderFactory;
use Espo\Core\Select\Where\Item;
use Espo\Core\Select\Where\ItemBuilder;
use Espo\Modules\Sales\Entities\PaymentAllocation;
use Espo\Modules\Sales\Entities\PaymentEntry;
use Espo\Modules\Sales\Entities\SupplierBill;
use Espo\Modules\Sales\Entities\SupplierCredit;
use Espo\Modules\Sales\Tools\Payment\PartyType;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\ORM\Name\Attribute;
use Espo\ORM\Query\Part\Condition as Cond;
use Espo\ORM\Query\Part\Expression as Expr;
use Espo\ORM\Query\Part\Selection;
use Espo\ORM\Query\Select;
use Espo\ORM\Query\SelectBuilder;
use RuntimeException;

class SupplierQueryHelper
{
    public function __construct(
        private SelectBuilderFactory $selectBuilderFactory,
        private Helper $helper,
    ) {}

    public function prepareSubQuery(
        string $entityType,
        ?Item $where,
        string $currency,
        ?Date $dateFrom = null,
        ?Date $dateTo = null,
        bool $isOpening = false,
        bool $isClosing = false,
        bool $isMiddle = false,
        ?bool $isOutbound = null,
        bool $noSum = false,
        ?string $sourceType = null,
        bool $isLoss = false,
    ): Select {

        $amountExpr = $this->prepareAmountExpr($entityType, $isLoss);
        $groupExpr = $this->prepareGroupExpr($entityType, $sourceType);

        $builder = $this->selectBuilderFactory
            ->create()
            ->from($entityType);

        if ($where) {
            $where = $this->excludeDateFromWhere($where);

            if ($entityType === PaymentAllocation::ENTITY_TYPE) {
                $where = $this->convertWhereForAllocation($sourceType, $where);
            }

            $builder->withWhere($where);
        }

        try {
            $queryBuilder = $builder->buildQueryBuilder();
        } catch (BadRequest|Forbidden $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }

        $this->applyRange(
            queryBuilder: $queryBuilder,
            dateFrom: $dateFrom,
            dateTo: $dateTo,
            isOpening: $isOpening,
            isClosing: $isClosing,
            isMiddle: $isMiddle,
        );

        if ($entityType === PaymentEntry::ENTITY_TYPE) {
            $queryBuilder->where([
                PaymentEntry::FIELD_PARTY_TYPE => PartyType::Supplier->value,
                OrderEntity::FIELD_STATUS . '!=' => PaymentEntry::STATUS_CANCELED,
            ]);

            if ($isOutbound === true) {
                $queryBuilder->where([PaymentEntry::FIELD_TYPE => PaymentEntry::TYPE_OUTBOUND]);
            } if ($isOutbound === false) {
                $queryBuilder->where([PaymentEntry::FIELD_TYPE => PaymentEntry::TYPE_INBOUND]);
            }
        } else if ($entityType === PaymentAllocation::ENTITY_TYPE) {
            if ($sourceType === PaymentEntry::ENTITY_TYPE) {
                $queryBuilder
                    ->leftJoin(PaymentAllocation::LINK_PAYMENT_ENTRY)
                    ->where([
                        PaymentAllocation::LINK_PAYMENT_ENTRY . '.' . PaymentEntry::FIELD_PARTY_TYPE =>
                            PartyType::Supplier->value,
                    ]);
            } else if ($sourceType === SupplierCredit::ENTITY_TYPE) {
                $queryBuilder->leftJoin(PaymentAllocation::LINK_SUPPLIER_CREDIT);
            }
        }

        $select = [Selection::create($groupExpr, 'group')];

        if (!$noSum) {
            $select[] = Selection::create($amountExpr, 'sumAmount');
        }

        if ($entityType !== PaymentAllocation::ENTITY_TYPE) {
            $queryBuilder->where([
                OrderEntity::FIELD_IS_ISSUED => true,
            ]);
        }

        if ($entityType === PaymentAllocation::ENTITY_TYPE) {
            if ($noSum) {
                $queryBuilder->where(
                    Cond::notEqual(
                        Expr::column(PaymentAllocation::FIELD_FX_GAIN_LOSS),
                        Expr::value(0)
                    )
                );
            } else {
                if (!$isLoss) {
                    $queryBuilder->where(
                        Cond::greater(
                            Expr::column(PaymentAllocation::FIELD_FX_GAIN_LOSS),
                            Expr::value(0)
                        )
                    );
                } else {
                    $queryBuilder->where(
                        Cond::less(
                            Expr::column(PaymentAllocation::FIELD_FX_GAIN_LOSS),
                            Expr::value(0)
                        )
                    );
                }
            }
        }

        return $queryBuilder
            ->select($select)
            ->where([
                OrderEntity::ATTR_AMOUNT_LOCAL_CURRENCY => $currency,
            ])
            ->where(
                Expr::isNotNull($groupExpr)
            )
            ->group($groupExpr)
            ->build();
    }

    private function prepareAmountExpr(string $entityType, bool $isLoss = false): Expr
    {
        $amountAttribute = OrderEntity::FIELD_GRAND_TOTAL_AMOUNT_LOCAL;

        if ($entityType === PaymentEntry::ENTITY_TYPE) {
            $amountAttribute = PaymentEntry::FIELD_AMOUNT_LOCAL;
        } else if ($entityType === PaymentAllocation::ENTITY_TYPE) {
            $amountAttribute = PaymentAllocation::FIELD_FX_GAIN_LOSS;
        }

        $expr = Expr::column($amountAttribute);

        if ($entityType === PaymentAllocation::ENTITY_TYPE && $isLoss) {
            $expr = Expr::subtract(Expr::value(0), $expr);
        }

        return Expr::sum($expr);
    }

    private function prepareGroupExpr(string $entityType, ?string $sourceType): Expr
    {
        $column = SupplierBill::ATTR_SUPPLIER_ID;

        if ($entityType === PaymentAllocation::ENTITY_TYPE) {
            $column = match ($sourceType) {
                PaymentEntry::ENTITY_TYPE => PaymentAllocation::LINK_PAYMENT_ENTRY . '.' . $column,
                SupplierCredit::ENTITY_TYPE => PaymentAllocation::LINK_SUPPLIER_CREDIT . '.' . $column,
                default => throw new RuntimeException(),
            };
        }

        return Expr::create($column);
    }

    private function excludeDateFromWhere(Item $where): Item
    {
        $itemList = $where->getItemList();

        $itemList = array_filter($itemList, fn ($it) => $it->getAttribute() !== OrderEntity::FIELD_POSTING_DATE);
        $itemList = array_values($itemList);

        return ItemBuilder::create()
            ->setType($where->getType())
            ->setItemList($itemList)
            ->build();
    }

    private function convertWhereForAllocation(?string $sourceType, Item $where): Item
    {
        $column = SupplierBill::ATTR_SUPPLIER_ID;

        $to = match ($sourceType) {
            SupplierCredit::ENTITY_TYPE => PaymentAllocation::LINK_SUPPLIER_CREDIT . '.' . $column,
            PaymentEntry::ENTITY_TYPE => PaymentAllocation::LINK_PAYMENT_ENTRY . '.' . $column,
            default => throw new RuntimeException(),
        };

        return $this->helper->changeWhereItem(
            item: $where,
            from: SupplierBill::ATTR_SUPPLIER_ID,
            to: $to,
        );
    }

    /**
     * @noinspection PhpUnusedParameterInspection
     */
    private function applyRange(
        SelectBuilder $queryBuilder,
        ?Date $dateFrom,
        ?Date $dateTo,
        bool $isOpening = false,
        bool $isClosing = false,
        bool $isMiddle = false,
    ): void {

        if ($isOpening && !$dateFrom) {
            $queryBuilder->where([Attribute::ID => null]);

            return;
        }

        if ($isClosing && !$dateTo) {
            return;
        }

        if (!$dateFrom && !$dateTo) {
            return;
        }

        $entityType = $queryBuilder->build()->getFrom();

        $attribute = match ($entityType) {
            SupplierBill::ENTITY_TYPE,
            SupplierCredit::ENTITY_TYPE,
            PaymentEntry::ENTITY_TYPE => OrderEntity::FIELD_POSTING_DATE,
            PaymentAllocation::ENTITY_TYPE => PaymentAllocation::FIELD_DATE,
            default => throw new RuntimeException(),
        };

        if ($isOpening) {
            $queryBuilder->where([
                $attribute . '<' => $dateFrom->toString(),
            ]);

            return;
        }

        if ($isClosing) {
            $queryBuilder->where([
                $attribute . '<=' => $dateTo->toString(),
            ]);

            return;
        }

        if ($dateFrom) {
            $queryBuilder->where([
                $attribute . '>=' => $dateFrom->toString(),
            ]);
        }

        if ($dateTo) {
            $queryBuilder->where([
                $attribute . '<=' => $dateTo->toString(),
            ]);
        }
    }
}
