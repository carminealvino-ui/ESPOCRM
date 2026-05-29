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
use Espo\Core\Select\Where\Item as WhereItem;
use Espo\Core\Select\Where\ItemBuilder;
use Espo\Core\Utils\Language;
use Espo\Entities\User;
use Espo\Modules\Advanced\Entities\Report;
use Espo\Modules\Advanced\Reports\GridReport;
use Espo\Modules\Advanced\Tools\Report\GridType\Data;
use Espo\Modules\Advanced\Tools\Report\GridType\Data\Rows;
use Espo\Modules\Advanced\Tools\Report\GridType\Result;
use Espo\Modules\Advanced\Tools\Report\ListType\Result as ListResult;
use Espo\Modules\Advanced\Tools\Report\ListType\SubReportParams;
use Espo\Modules\Crm\Entities\Account;
use Espo\Modules\Sales\Entities\CreditNote;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Entities\Product;
use Espo\Modules\Sales\Entities\TaxCode;
use Espo\Modules\Sales\Entities\TaxTotalItem;
use Espo\Modules\Advanced\Tools\Report\GridType\Data\Column;
use Espo\Modules\Advanced\Tools\Report\GridType\Data\Group;
use Espo\Modules\Advanced\Tools\Report\GridType\ResultData;
use Espo\Modules\Advanced\Tools\Report\GridType\ResultPreparator;
use Espo\Modules\Advanced\Tools\Report\GridType\Data\SwitchItem;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\ORM\EntityManager;
use Espo\ORM\Name\Attribute;
use Espo\ORM\Query\Part\Condition as Cond;
use Espo\ORM\Query\Part\Expression as Expr;
use Espo\ORM\Query\Part\Join;
use Espo\ORM\Query\Part\Selection;
use Espo\ORM\Query\Select;
use Espo\ORM\Query\SelectBuilder;
use Espo\ORM\Query\UnionBuilder;
use PDO;
use RuntimeException;

/**
 * @noinspection PhpUnused
 */
class TaxSalesList implements GridReport
{
    private const ALIAS_BASE_AMOUNT = 'SUM:amount';
    private const ALIAS_ROLE = 'taxCode.role';
    private const ALIAS_COUNTRY = 'taxCode.country';
    private const ALIAS_TAX_NUMBER = 'account.taxNumber';

    private const GROUP_ACCOUNT = 'account';
    private const GROUP_TAX_CODE = 'taxCode';

    private const COLUMN_SOURCE_ACCOUNT_ID = 'source.' . OrderEntity::ATTR_ACCOUNT_ID;
    private const COLUMN_SOURCE_IS_ISSUED = 'source.' . OrderEntity::FIELD_IS_ISSUED;

    private const SWITCH_INVOICES = 'invoices';
    private const SWITCH_CREDIT_NOTES = 'creditNotes';

    public function __construct(
        private EntityManager $entityManager,
        private SelectBuilderFactory $selectBuilderFactory,
        private Report $report,
        private Language $defaultLanguage,
        private ConfigDataProvider $configDataProvider,
        private ResultPreparator $resultPreparator,
    ) {}

    public function run(?WhereItem $where, ?User $user): Result
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

        $accountId = $subReportParams->getGroupValue();
        $taxCodeId = $subReportParams->getGroupValue2();

        $entityType = match ($subReportParams->getTarget()) {
            self::SWITCH_INVOICES => Invoice::ENTITY_TYPE,
            self::SWITCH_CREDIT_NOTES => CreditNote::ENTITY_TYPE,
        };

        $dateField = $this->getDateColumn($entityType);

        $where = $this->obtainWhereItem($searchParams->getWhere(), "source.$dateField");

        $searchParams = $searchParams->withWhere($where);

        $queryBuilder = $this->selectBuilderFactory
            ->create()
            ->from(TaxTotalItem::ENTITY_TYPE)
            ->withSearchParams($searchParams)
            ->buildQueryBuilder();

        if ($taxCodeId) {
            $queryBuilder->where([
                TaxTotalItem::ATTR_TAX_CODE_ID => $taxCodeId,
            ]);
        }

        $query = $queryBuilder
            ->join(
                Join::createWithTableTarget($entityType, 'source')
                    ->withConditions(
                        Cond::and(
                            Cond::equal(
                                Expr::column('source.id'),
                                Expr::column('sourceId'),
                            ),
                            Cond::equal(
                                Expr::column('sourceType'),
                                Expr::value($entityType)
                            ),
                        )
                    )
            )
            ->where([
                "source." . OrderEntity::FIELD_IS_ISSUED => true,
                "source." . $dateField . '!=' => null,
                "source." . OrderEntity::ATTR_ACCOUNT_ID => $accountId,
                TaxTotalItem::ATTR_AMOUNT_LOCAL_CURRENCY => $currency,
                [TaxTotalItem::ATTR_TAX_CODE_ID => $this->getTaxCodeIds()],
                'sourceType' => $entityType,
            ])
            ->build();

        $collection = $this->entityManager->getRDBRepositoryByClass(TaxTotalItem::class)->clone($query)->find();
        $total = $this->entityManager->getRDBRepositoryByClass(TaxTotalItem::class)->clone($query)->count();

        foreach ($collection as $entity) {
            $entity->loadParentNameField(TaxTotalItem::FIELD_SOURCE);
        }

        return new ListResult($collection, $total);
    }

    private function prepareQuery(?WhereItem $where, string $currency): Select
    {
        $sumBaseAmountExpression =
            Expr::subtract(
                Expr::coalesce(Expr::alias('i.sumBaseAmount'), Expr::value(0.0)),
                Expr::coalesce(Expr::alias('c.sumBaseAmount'), Expr::value(0.0)),
            );

        $groupAccountExpression = Expr::alias('k.accountId');
        $groupTaxCodeExpression = Expr::alias('k.taxCodeId');

        return SelectBuilder::create()
            ->select([
                Selection::create($sumBaseAmountExpression, self::ALIAS_BASE_AMOUNT),
                Selection::create($groupAccountExpression, self::GROUP_ACCOUNT),
                Selection::create($groupTaxCodeExpression, self::GROUP_TAX_CODE),
                Selection::create(Expr::column('taxCode.name'), 'taxCodeName'),
                Selection::create(Expr::column('taxCode.order'), 'taxCodeOrder'),
                Selection::create(Expr::column('taxCode.country'), self::ALIAS_COUNTRY),
                Selection::create(Expr::column('account.name'), 'accountName'),
                Selection::create(Expr::column('account.taxNumber'), self::ALIAS_TAX_NUMBER),
            ])
            ->fromQuery(
                UnionBuilder::create()
                    ->query(
                        $this->prepareSubQuery($where, $currency, Invoice::ENTITY_TYPE, true),
                    )
                    ->query(
                        $this->prepareSubQuery($where, $currency, CreditNote::ENTITY_TYPE, true),
                    )
                    ->build(),
                'k'
            )
            ->leftJoin(
                Join
                    ::createWithSubQuery(
                        $this->prepareSubQuery($where, $currency, Invoice::ENTITY_TYPE),
                        'i'
                    )
                    ->withConditions(
                        Cond::and(
                            Expr::equal(Expr::alias('k.taxCodeId'), Expr::alias('i.taxCodeId')),
                            Expr::equal(Expr::alias('k.accountId'), Expr::alias('i.accountId')),
                        )
                    ),
            )
            ->leftJoin(
                Join
                    ::createWithSubQuery(
                        $this->prepareSubQuery($where, $currency, CreditNote::ENTITY_TYPE),
                        'c'
                    )
                    ->withConditions(
                        Cond::and(
                            Expr::equal(Expr::alias('k.taxCodeId'), Expr::alias('c.taxCodeId')),
                            Expr::equal(Expr::alias('k.accountId'), Expr::alias('c.accountId')),
                        )
                    ),
            )
            ->leftJoin(
                Join::createWithTableTarget(Account::ENTITY_TYPE, 'account')
                    ->withConditions(
                        Cond::and(
                            Expr::equal(
                                Expr::column('account.id'),
                                Expr::alias('k.accountId'),
                            ),
                            Expr::equal(
                                Expr::column('account.' . Attribute::DELETED),
                                false,
                            )
                        )
                    )
            )
            ->leftJoin(
                Join::createWithTableTarget(TaxCode::ENTITY_TYPE, 'taxCode')
                    ->withConditions(
                        Cond::and(
                            Expr::equal(
                                Expr::column('taxCode.id'),
                                Expr::alias('k.taxCodeId'),
                            ),
                            Expr::equal(
                                Expr::column('taxCode.' . Attribute::DELETED),
                                false,
                            )
                        )
                    )
            )
            ->order('account.name')
            ->order('taxCode.order')
            ->build();
    }

    private function prepareSubQuery(
        ?WhereItem $where,
        string $currency,
        string $entityType,
        bool $noSum = false,
    ): Select {

        $dateField = $this->getDateColumn($entityType);
        $where = $this->obtainWhereItem($where, "source.$dateField");

        $builder = $this->selectBuilderFactory
            ->create()
            ->from(TaxTotalItem::ENTITY_TYPE);

        if ($where) {
            $builder = $builder->withWhere($where);
        }

        try {
            $queryBuilder = $builder->buildQueryBuilder();
        } catch (BadRequest|Forbidden $e) {
            throw new RuntimeException(previous: $e);
        }

        $select = [
            Selection::create(Expr::column(TaxTotalItem::ATTR_TAX_CODE_ID), 'taxCodeId'),
            Selection::create(Expr::column(self::COLUMN_SOURCE_ACCOUNT_ID), 'accountId'),
        ];

        if (!$noSum) {
            $select[] = Selection::create(
                Expr::sum(Expr::column(TaxTotalItem::FIELD_BASE_AMOUNT_LOCAL)),
                'sumBaseAmount',
            );
        }

        return $queryBuilder
            ->select($select)
            ->join(
                Join::createWithTableTarget($entityType, 'source')
                    ->withConditions(
                        Cond::and(
                            Cond::equal(
                                Expr::column('source' . '.' . Attribute::ID),
                                Expr::column(TaxTotalItem::ATTR_SOURCE_ID),
                            ),
                            Cond::equal(
                                Expr::column(TaxTotalItem::ATTR_SOURCE_TYPE),
                                Expr::value($entityType)
                            ),
                        )
                    )
            )
            ->where([
                self::COLUMN_SOURCE_IS_ISSUED => true,
                self::COLUMN_SOURCE_ACCOUNT_ID . '!=' => null,
                "source." . $dateField . '!=' => null,
                TaxTotalItem::ATTR_AMOUNT_LOCAL_CURRENCY => $currency,
                TaxTotalItem::ATTR_TAX_CODE_ID => $this->getTaxCodeIds(),
                TaxTotalItem::ATTR_SOURCE_TYPE => $entityType,
            ])
            ->group(self::COLUMN_SOURCE_ACCOUNT_ID)
            ->group(TaxTotalItem::ATTR_TAX_CODE_ID)
            ->build();
    }

    private function getDateColumn(string $entityType): string
    {
        return match ($entityType) {
            Invoice::ENTITY_TYPE, CreditNote::ENTITY_TYPE => OrderEntity::FIELD_POSTING_DATE,
            default => throw new RuntimeException(),
        };
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

    /**
     * @param array<string, mixed>[] $rows
     */
    private function prepareResult(array $rows, ?WhereItem $where, string $currency): Result
    {
        $this->populateRoles($rows);

        $baseAmountLabel = $this->defaultLanguage->translateLabel('amount', 'salesReportColumns', Report::ENTITY_TYPE);
        $roleLabel = $this->defaultLanguage->translateLabel('itemType', 'fields', Product::ENTITY_TYPE);
        $taxNumberLabel = $this->defaultLanguage->translateLabel('taxNumber', 'fields', Account::ENTITY_TYPE);
        $countryLabel = $this->defaultLanguage->translateLabel('country', 'fields', TaxCode::ENTITY_TYPE);
        $groupAccountLabel = $this->defaultLanguage->translateLabel(Account::ENTITY_TYPE, 'scopeNames');
        $groupTaxCodeLabel = $this->defaultLanguage->translateLabel(TaxCode::ENTITY_TYPE, 'scopeNames');

        $data = new ResultData(
            entityType: Invoice::ENTITY_TYPE,
            group: new Group(
                name: self::GROUP_ACCOUNT,
                label: $groupAccountLabel,
                valueLabelKey: 'accountName',
            ),
            columns: [
                new Column(
                    name: self::ALIAS_COUNTRY,
                    label: $countryLabel,
                    fieldType: FieldType::VARCHAR,
                    type: Data\ColumnType::NonSummary,
                    isNumeric: false,
                    isAggregated: true,
                ),
                new Column(
                    name: self::ALIAS_TAX_NUMBER,
                    label: $taxNumberLabel,
                    fieldType: FieldType::VARCHAR,
                    type: Data\ColumnType::NonSummary,
                    isNumeric: false,
                    isAggregated: true,
                ),
                new Column(
                    name: self::ALIAS_ROLE,
                    label: $roleLabel,
                    fieldType: FieldType::VARCHAR,
                    type: Data\ColumnType::NonSummary,
                    isNumeric: false,
                    isAggregated: true,
                ),
                new Column(
                    name: self::ALIAS_BASE_AMOUNT,
                    label: $baseAmountLabel,
                    fieldType: FieldType::CURRENCY,
                ),
            ],
            secondGroup: new Group(
                name: self::GROUP_TAX_CODE,
                label: $groupTaxCodeLabel,
                valueLabelKey: 'taxCodeName',
            ),
            currency: $currency,
            switchItems: [
                new SwitchItem(
                    name: self::SWITCH_INVOICES,
                    label: $this->defaultLanguage->translateLabel(Invoice::ENTITY_TYPE, 'scopeNamesPlural'),
                    entityType: TaxTotalItem::ENTITY_TYPE,
                ),
                new SwitchItem(
                    name: self::SWITCH_CREDIT_NOTES,
                    label: $this->defaultLanguage->translateLabel(CreditNote::ENTITY_TYPE, 'scopeNamesPlural'),
                    entityType: TaxTotalItem::ENTITY_TYPE,
                ),
            ],
            noSubReport: false,
            tableMode: Data::TABLE_MODE_NORMALIZED,
        );

        return $this->resultPreparator->prepare($data, Rows::fromAssocList($rows), $where);
    }

    /**
     * @param array<string, mixed>[] $rows
     */
    private function populateRoles(array &$rows): void
    {
        foreach ($rows as &$row) {
            $taxCodeId = $row[self::GROUP_TAX_CODE] ?? null;

            if (!$taxCodeId) {
                $row[self::ALIAS_ROLE] = null;

                continue;
            }

            $row[self::ALIAS_ROLE] = $this->report->getInternalParams()->taxCodesColumns->$taxCodeId->role ?? null;
        }
    }
}
