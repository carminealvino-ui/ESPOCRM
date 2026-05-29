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

namespace Espo\Modules\Sales\Tools\Report\Reports;

use Espo\Core\Currency\ConfigDataProvider;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\ORM\Type\FieldType;
use Espo\Core\Select\SearchParams;
use Espo\Core\Select\SelectBuilderFactory;
use Espo\Core\Select\Where\Item;
use Espo\Core\Select\Where\ItemBuilder;
use Espo\Core\Utils\Language;
use Espo\Entities\User;
use Espo\Modules\Advanced\Entities\Report;
use Espo\Modules\Advanced\Reports\GridReport;
use Espo\Modules\Advanced\Tools\Report\GridType\Data\Column;
use Espo\Modules\Advanced\Tools\Report\GridType\Data\ColumnType;
use Espo\Modules\Advanced\Tools\Report\GridType\Data\Group;
use Espo\Modules\Advanced\Tools\Report\GridType\Data\Rows;
use Espo\Modules\Advanced\Tools\Report\GridType\Data\SwitchItem;
use Espo\Modules\Advanced\Tools\Report\GridType\Result;
use Espo\Modules\Advanced\Tools\Report\GridType\ResultData;
use Espo\Modules\Advanced\Tools\Report\GridType\ResultPreparator;
use Espo\Modules\Advanced\Tools\Report\ListType\Result as ListResult;
use Espo\Modules\Advanced\Tools\Report\ListType\SubReportParams;
use Espo\Modules\Sales\Entities\PaymentEntry;
use Espo\Modules\Sales\Entities\SupplierBill;
use Espo\Modules\Sales\Entities\SupplierCredit;
use Espo\Modules\Sales\Entities\TaxAllocationItem;
use Espo\Modules\Sales\Entities\TaxCode;
use Espo\Modules\Sales\Entities\TaxLineItem;
use Espo\Modules\Sales\Tools\Payment\PartyType;
use Espo\Modules\Sales\Tools\Payment\Type;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\ORM\EntityManager;
use Espo\ORM\Name\Attribute;
use Espo\ORM\Query\Part\Expression as Expr;
use Espo\ORM\Query\Part\Join;
use Espo\ORM\Query\Part\Selection;
use Espo\ORM\Query\Select;
use Espo\ORM\Query\SelectBuilder;
use PDO;
use RuntimeException;

class PurchaseTaxSummaryCashBasis implements GridReport
{
    private const ALIAS_BASE_AMOUNT = 'SUM:baseAmount';
    private const ALIAS_AMOUNT = 'SUM:amount';
    private const ALIAS_CODE = 'code';

    private const GROUP = 'taxCodeId';

    private const SWITCH_SUPPLIER_BULLS = 'supplierBills';
    private const SWITCH_SUPPLIER_CREDITS = 'supplierCredits';

    public function __construct(
        private EntityManager $entityManager,
        private SelectBuilderFactory $selectBuilderFactory,
        private Report $report,
        private Language $defaultLanguage,
        private ConfigDataProvider $configDataProvider,
        private ResultPreparator $resultPreparator,
    ) {}

    public function run(?Item $where, ?User $user): Result
    {
        $currency = $this->configDataProvider->getBaseCurrency();

        $query = $this->prepareQuery($where, $currency);

        $rows = $this->entityManager
            ->getQueryExecutor()
            ->execute($query)
            ->fetchAll(PDO::FETCH_ASSOC);

        return $this->prepareResult(
            rows: $rows,
            where: $where,
            currency: $currency,
        );
    }

    public function runSubReport(SearchParams $searchParams, SubReportParams $subReportParams, ?User $user): ListResult
    {
        $currency = $this->configDataProvider->getBaseCurrency();
        $taxCodeId = $subReportParams->getGroupValue();

        $link = TaxAllocationItem::FIELD_PAYMENT_ENTRY;

        $dateField = $this->getDateColumn();

        $where = $this->obtainWhereItem($searchParams->getWhere(), $link . '.' . $dateField);

        $type = match ($subReportParams->getTarget()) {
            self::SWITCH_SUPPLIER_BULLS => Type::Outbound->value,
            self::SWITCH_SUPPLIER_CREDITS => Type::Inbound->value,
        };

        $searchParams = $searchParams->withWhere($where);

        $queryBuilder = $this->selectBuilderFactory
            ->create()
            ->from(TaxAllocationItem::ENTITY_TYPE)
            ->withSearchParams($searchParams)
            ->buildQueryBuilder();

        $query = $queryBuilder
            ->join(
                Join::createWithRelationTarget($link)
            )
            ->where([
                $link . '.' . PaymentEntry::FIELD_PARTY_TYPE => PartyType::Supplier->value,
                $link . '.' . PaymentEntry::FIELD_TYPE => $type,
                $link . '.' . OrderEntity::FIELD_IS_ISSUED => true,
                $link . '.' . $dateField . '!=' => null,
                TaxAllocationItem::ATTR_AMOUNT_LOCAL_CURRENCY => $currency,
                [TaxAllocationItem::ATTR_TAX_CODE_ID => $this->getTaxCodeIds()],
                [TaxAllocationItem::ATTR_TAX_CODE_ID => $taxCodeId],
            ])
            ->build();

        $collection = $this->entityManager->getRDBRepositoryByClass(TaxAllocationItem::class)->clone($query)->find();
        $total = $this->entityManager->getRDBRepositoryByClass(TaxAllocationItem::class)->clone($query)->count();

        foreach ($collection as $entity) {
            $entity->loadParentNameField(TaxAllocationItem::FIELD_SOURCE);
        }

        return new ListResult($collection, $total);
    }

    private function prepareQuery(?Item $where, string $currency): Select
    {
        $sumBaseAmountExpression =
            Expr::subtract(
                Expr::coalesce(Expr::alias('i.sumBaseAmount'), Expr::value(0.0)),
                Expr::coalesce(Expr::alias('c.sumBaseAmount'), Expr::value(0.0)),
            );

        $sumAmountExpression =
            Expr::subtract(
                Expr::coalesce(Expr::alias('i.sumAmount'), Expr::value(0.0)),
                Expr::coalesce(Expr::alias('c.sumAmount'), Expr::value(0.0)),
            );

        $groupExpression = Expr::alias('k.id');

        return SelectBuilder::create()
            ->select([
                Selection::create($sumBaseAmountExpression, self::ALIAS_BASE_AMOUNT),
                Selection::create($sumAmountExpression, self::ALIAS_AMOUNT),
                Selection::create($groupExpression, self::GROUP),
                Selection::create(Expr::column('k.code'), 'code'),
                Selection::create(Expr::column('k.name'), 'name'),
            ])
            ->fromQuery(
                SelectBuilder::create()
                    ->from(TaxCode::ENTITY_TYPE)
                    ->select([
                        Attribute::ID,
                        TaxCode::FILED_CODE,
                        [TaxCode::FIELD_ORDER, 'order'],
                        [TaxCode::FIELD_NAME, 'name'],
                    ])
                    ->where([
                        Attribute::ID => $this->getTaxCodeIds(),
                    ])
                    ->build(),
                'k'
            )
            ->leftJoin(
                Join
                    ::createWithSubQuery(
                        $this->prepareSubQuery($where, $currency, SupplierBill::ENTITY_TYPE),
                        'i'
                    )
                    ->withConditions(
                        Expr::equal(Expr::alias('k.id'), Expr::alias('i.taxCodeId'))
                    ),
            )
            ->leftJoin(
                Join
                    ::createWithSubQuery(
                        $this->prepareSubQuery($where, $currency, SupplierCredit::ENTITY_TYPE),
                        'c'
                    )
                    ->withConditions(
                        Expr::equal(Expr::alias('k.id'), Expr::alias('c.taxCodeId'))
                    ),
            )
            ->order('k.order')
            ->build();
    }

    private function obtainWhereItem(?Item $where, ?string $replace = null): ?Item
    {
        if (!$where) {
            return null;
        }

        foreach ($where->getItemList() as $it) {
            if ($it->getAttribute() === OrderEntity::FIELD_POSTING_DATE) {
                if ($replace) {
                    $it = ItemBuilder::create()
                        ->setAttribute($replace)
                        ->setType($it->getType())
                        ->setValue($it->getValue())
                        ->build();
                }

                return $it;
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function getTaxCodeIds(): array
    {
        return $this->report->getInternalParams()->taxCodesIds ?? [];
    }

    private function prepareSubQuery(?Item $where, string $currency, string $entityType): Select
    {
        $link = TaxAllocationItem::FIELD_PAYMENT_ENTRY;

        $dateField = $this->getDateColumn();

        $where = $this->obtainWhereItem($where, $link . '.' . $dateField);

        $builder = $this->selectBuilderFactory
            ->create()
            ->from(TaxAllocationItem::ENTITY_TYPE);

        if ($where) {
            $builder = $builder->withWhere($where);
        }

        try {
            $queryBuilder = $builder->buildQueryBuilder();
        } catch (BadRequest|Forbidden $e) {
            throw new RuntimeException(previous: $e);
        }

        $select = [
            Selection::create(Expr::column(TaxAllocationItem::ATTR_TAX_CODE_ID), 'taxCodeId'),
        ];

        $select[] = Selection::create(
            Expr::sum(Expr::column(TaxAllocationItem::FIELD_BASE_AMOUNT_LOCAL)),
            'sumBaseAmount',
        );

        $select[] = Selection::create(
            Expr::sum(Expr::column(TaxAllocationItem::FIELD_AMOUNT_LOCAL)),
            'sumAmount',
        );

        $type = match ($entityType) {
            SupplierBill::ENTITY_TYPE => Type::Outbound->value,
            SupplierCredit::ENTITY_TYPE => Type::Inbound->value,
        };

        return $queryBuilder
            ->select($select)
            ->join(
                Join::createWithRelationTarget($link)
            )
            ->where([
                $link . '.' . PaymentEntry::FIELD_PARTY_TYPE => PartyType::Supplier->value,
                $link . '.' . PaymentEntry::FIELD_TYPE => $type,
                $link . '.' . OrderEntity::FIELD_IS_ISSUED => true,
                $link . '.' . $dateField . '!=' => null,
                TaxAllocationItem::ATTR_AMOUNT_LOCAL_CURRENCY => $currency,
                [TaxAllocationItem::ATTR_TAX_CODE_ID => $this->getTaxCodeIds()],
            ])
            ->group(TaxAllocationItem::ATTR_TAX_CODE_ID)
            ->build();
    }

    /**
     * @param array<string, mixed>[] $rows
     */
    private function prepareResult(array $rows, ?Item $where, string $currency): Result
    {
        $baseAmountLabel = $this->defaultLanguage->translateLabel('baseAmount', 'fields', TaxLineItem::ENTITY_TYPE);
        $amountLabel = $this->defaultLanguage->translateLabel('amount', 'fields', TaxLineItem::ENTITY_TYPE);
        $codeLabel = $this->defaultLanguage->translateLabel('code', 'fields', TaxCode::ENTITY_TYPE);
        $groupLabel = $this->defaultLanguage->translateLabel(TaxCode::ENTITY_TYPE, 'scopeNames');

        $columns = [
            new Column(
                name: self::ALIAS_BASE_AMOUNT,
                label: $baseAmountLabel,
                fieldType: FieldType::CURRENCY,
            ),
            new Column(
                name: self::ALIAS_AMOUNT,
                label: $amountLabel,
                fieldType: FieldType::CURRENCY,
            ),
        ];

        // Not implemented.
        if ($this->report->getInternalParams()->codeColumn ?? false) {
            array_unshift(
                $columns,
                new Column(
                    name: self::ALIAS_CODE,
                    label: $codeLabel,
                    fieldType: FieldType::VARCHAR,
                    type: ColumnType::NonSummary,
                    isNumeric: false,
                    isAggregated: true,
                )
            );
        }

        $data = new ResultData(
            entityType: SupplierBill::ENTITY_TYPE,
            group: new Group(
                name: self::GROUP,
                label: $groupLabel,
                valueLabelKey: 'name',
            ),
            columns: $columns,
            currency: $currency,
            switchItems: [
                new SwitchItem(
                    name: self::SWITCH_SUPPLIER_BULLS,
                    label: $this->defaultLanguage->translateLabel(SupplierBill::ENTITY_TYPE, 'scopeNamesPlural'),
                    entityType: TaxAllocationItem::ENTITY_TYPE,
                ),
                new SwitchItem(
                    name: self::SWITCH_SUPPLIER_CREDITS,
                    label: $this->defaultLanguage->translateLabel(SupplierCredit::ENTITY_TYPE, 'scopeNamesPlural'),
                    entityType: TaxAllocationItem::ENTITY_TYPE,
                ),
            ],
            noSubReport: false,
        );

        return $this->resultPreparator->prepare($data, Rows::fromAssocList($rows), $where);
    }

    private function getDateColumn(): string
    {
        return OrderEntity::FIELD_POSTING_DATE;
    }
}
