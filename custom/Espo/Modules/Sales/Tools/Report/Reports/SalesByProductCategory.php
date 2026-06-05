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
use Espo\Core\Utils\Language;
use Espo\Entities\User;
use Espo\Modules\Advanced\Entities\Report;
use Espo\Modules\Advanced\Reports\GridReport;
use Espo\Modules\Advanced\Tools\Report\GridType\Data\Rows;
use Espo\Modules\Advanced\Tools\Report\GridType\Result;
use Espo\Modules\Advanced\Tools\Report\ListType\Result as ListResult;
use Espo\Modules\Advanced\Tools\Report\ListType\SubReportParams;
use Espo\Modules\Sales\Entities\CreditNote;
use Espo\Modules\Sales\Entities\CreditNoteItem;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Entities\InvoiceItem;
use Espo\Modules\Sales\Entities\ProductCategory;
use Espo\Modules\Sales\Entities\QuoteItem;
use Espo\Modules\Advanced\Tools\Report\GridType\Data\Column;
use Espo\Modules\Advanced\Tools\Report\GridType\ResultPreparator;
use Espo\Modules\Advanced\Tools\Report\GridType\Data\Group;
use Espo\Modules\Advanced\Tools\Report\GridType\ResultData;
use Espo\Modules\Advanced\Tools\Report\GridType\Data\SwitchItem;
use Espo\Modules\Sales\Tools\Report\Helper\Helper;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\ORM\EntityManager;
use Espo\ORM\Name\Attribute;
use Espo\ORM\Query\Part\Condition as Cond;
use Espo\ORM\Query\Part\Expression as Expr;
use Espo\ORM\Query\Part\Join;
use Espo\ORM\Query\Part\Order;
use Espo\ORM\Query\Part\Selection;
use Espo\ORM\Query\Select;
use Espo\ORM\Query\SelectBuilder;
use Espo\ORM\Query\UnionBuilder;
use PDO;
use RuntimeException;

/**
 * @noinspection PhpUnused
 */
class SalesByProductCategory implements GridReport
{
    private const ALIAS_QUANTITY = 'SUM:quantity';
    private const ALIAS_AMOUNT = 'SUM:amount';

    private const SWITCH_INVOICES = 'invoices';
    private const SWITCH_CREDIT_NOTES = 'creditNotes';

    private const ALIAS_CATEGORY = 'productCategory';
    private const EXPR_GROUP = self::ALIAS_CATEGORY . '.' . Attribute::ID;

    private const LIMIT = 1000;

    public function __construct(
        private EntityManager $entityManager,
        private SelectBuilderFactory $selectBuilderFactory,
        private Report $report,
        private Language $defaultLanguage,
        private ConfigDataProvider $configDataProvider,
        private Helper $helper,
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

        $productCategoryId = $subReportParams->getGroupValue();

        if (!$productCategoryId) {
            throw new BadRequest("No group value.");
        }

        $where = $searchParams->getWhere();

        if ($subReportParams->getTarget() === self::SWITCH_INVOICES || !$subReportParams->getTarget()) {
            if ($where) {
                $where = $this->helper->changeWhereItem(
                    item: $where,
                    from: OrderEntity::FIELD_POSTING_DATE,
                    to: OrderEntity::FIELD_POSTING_DATE,
                );

                $searchParams = $searchParams->withWhere($where);
            }

            $queryBuilder = $this->selectBuilderFactory
                ->create()
                ->from(InvoiceItem::ENTITY_TYPE)
                ->forUser($user)
                ->withSearchParams($searchParams)
                ->withStrictAccessControl()
                ->buildQueryBuilder();

            $queryBuilder->join(QuoteItem::FIELD_PRODUCT);

            $this->applyCategory($queryBuilder);

            $query = $queryBuilder
                ->where([
                    'invoice.' . OrderEntity::FIELD_IS_ISSUED => true,
                    'invoice.' . OrderEntity::FIELD_POSTING_DATE . '!=' => null,
                    'invoice.' . OrderEntity::ATTR_AMOUNT_LOCAL_CURRENCY => $currency,
                    QuoteItem::ATTR_PRODUCT_ID . '!=' => null,
                    self::ALIAS_CATEGORY .'.' . Attribute::ID => $productCategoryId,
                ])
                ->build();

            $collection = $this->entityManager->getRDBRepositoryByClass(InvoiceItem::class)->clone($query)->find();
            $total = $this->entityManager->getRDBRepositoryByClass(InvoiceItem::class)->clone($query)->count();
        } else if ($subReportParams->getTarget() === self::SWITCH_CREDIT_NOTES) {
            if ($where) {
                $where = $this->helper->changeWhereItem(
                    item: $where,
                    from: OrderEntity::FIELD_POSTING_DATE,
                    to: OrderEntity::FIELD_POSTING_DATE,
                );

                $searchParams = $searchParams->withWhere($where);
            }

            $queryBuilder = $this->selectBuilderFactory
                ->create()
                ->from(CreditNoteItem::ENTITY_TYPE)
                ->forUser($user)
                ->withSearchParams($searchParams)
                ->withStrictAccessControl()
                ->buildQueryBuilder();

            $queryBuilder->join(QuoteItem::FIELD_PRODUCT);

            $this->applyCategory($queryBuilder);

            $query = $queryBuilder
                ->where([
                    'creditNote.' . OrderEntity::FIELD_IS_ISSUED => true,
                    'creditNote.' . OrderEntity::FIELD_POSTING_DATE . '!=' => null,
                    'creditNote.' . OrderEntity::ATTR_AMOUNT_LOCAL_CURRENCY => $currency,
                    QuoteItem::ATTR_PRODUCT_ID . '!=' => null,
                    self::ALIAS_CATEGORY .'.' . Attribute::ID => $productCategoryId,
                ])
                ->build();

            $collection = $this->entityManager->getRDBRepositoryByClass(CreditNoteItem::class)->clone($query)->find();
            $total = $this->entityManager->getRDBRepositoryByClass(CreditNoteItem::class)->clone($query)->count();
        } else {
            throw new RuntimeException();
        }

        return new ListResult($collection, $total);
    }

    private function prepareQuery(?Item $where, string $currency): Select
    {
        $quantityExpression =
            Expr::subtract(
                Expr::coalesce(Expr::alias('i.sumQuantity'), Expr::value(0.0)),
                Expr::coalesce(Expr::alias('c.sumQuantity'), Expr::value(0.0)),
            );

        $amountExpression =
            Expr::subtract(
                Expr::coalesce(Expr::alias('i.sumAmount'), Expr::value(0.0)),
                Expr::coalesce(Expr::alias('c.sumAmount'), Expr::value(0.0)),
            );

        $groupExpression = Expr::coalesce(Expr::alias('i.group'), Expr::alias('c.group'));

        $orderByExpr = $amountExpression;
        $orderDirection = Order::DESC;

        if (
            ($this->report->getInternalParams()->orderByField ?? null) === 'productCategory'
        ) {
            $orderByExpr = Expr::column(self::ALIAS_CATEGORY . '.order');
            $orderDirection = Order::ASC;
        }

        return SelectBuilder::create()
            ->select([
                Selection::create($quantityExpression, self::ALIAS_QUANTITY),
                Selection::create($amountExpression, self::ALIAS_AMOUNT),
                Selection::create($groupExpression, 'productCategoryId'),
                Selection::create(Expr::column(self::ALIAS_CATEGORY . '.name'), 'productCategoryName'),
                Selection::create(Expr::column(self::ALIAS_CATEGORY . '.order'), 'productCategoryOrder'),
            ])
            ->fromQuery(
                UnionBuilder::create()
                    ->query(
                        $this->prepareInvoiceQuery($where, $currency, true),
                    )
                    ->query(
                        $this->prepareCreditNoteQuery($where, $currency, true),
                    )
                    ->build(),
                'k'
            )
            ->leftJoin(
                Join
                    ::createWithSubQuery(
                        $this->prepareInvoiceQuery($where, $currency),
                        'i'
                    )
                    ->withConditions(
                        Expr::equal(Expr::column('k.group'), Expr::column('i.group'))
                    ),
            )
            ->leftJoin(
                Join
                    ::createWithSubQuery(
                        $this->prepareCreditNoteQuery($where, $currency),
                        'c'
                    )
                    ->withConditions(
                        Expr::equal(Expr::column('k.group'), Expr::column('c.group'))
                    ),
            )
            ->leftJoin(
                Join::createWithTableTarget(ProductCategory::ENTITY_TYPE, self::ALIAS_CATEGORY)
                    ->withConditions(
                        Cond::and(
                            Expr::equal(
                                Expr::column(self::ALIAS_CATEGORY . '.' . Attribute::ID),
                                Expr::column('k.group'),
                            ),
                            Expr::equal(
                                Expr::column(self::ALIAS_CATEGORY . '.' . Attribute::DELETED),
                                false,
                            )
                        )
                    )
            )
            ->order($orderByExpr, $orderDirection)
            ->limit(0, self::LIMIT)
            ->build();
    }

    private function prepareInvoiceQuery(?Item $where, string $currency, bool $noSum = false): Select
    {
        $builder = $this->selectBuilderFactory
            ->create()
            ->from(InvoiceItem::ENTITY_TYPE);

        if ($where) {
            $where = $this->helper->changeWhereItem(
                item: $where,
                from: OrderEntity::FIELD_POSTING_DATE,
                to: OrderEntity::FIELD_POSTING_DATE,
            );

            $builder->withWhere($where);
        }

        try {
            $queryBuilder = $builder->buildQueryBuilder();
        } catch (BadRequest|Forbidden $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }

        $select = $this->prepareSelect($noSum);

        $queryBuilder
            ->select($select)
            ->join(QuoteItem::FIELD_PRODUCT);

        $this->applyCategory($queryBuilder);

        return $queryBuilder
            ->leftJoin('invoice')
            ->where([
                QuoteItem::ATTR_PRODUCT_ID . '!=' => null,
                'invoice.' . OrderEntity::FIELD_IS_ISSUED => true,
                'invoice.' . OrderEntity::FIELD_POSTING_DATE . '!=' => null,
                'invoice.' . OrderEntity::ATTR_AMOUNT_LOCAL_CURRENCY => $currency,
            ])
            ->group(self::EXPR_GROUP)
            ->build();
    }

    private function prepareCreditNoteQuery(?Item $where, string $currency, bool $noSum = false): Select
    {
        $builder = $this->selectBuilderFactory
            ->create()
            ->from(CreditNoteItem::ENTITY_TYPE);

        if ($where) {
            $where = $this->helper->changeWhereItem(
                item: $where,
                from: OrderEntity::FIELD_POSTING_DATE,
                to: OrderEntity::FIELD_POSTING_DATE,
            );

            $builder->withWhere($where);
        }

        try {
            $queryBuilder = $builder->buildQueryBuilder();
        } catch (BadRequest|Forbidden $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }

        $select = $this->prepareSelect($noSum);

        $queryBuilder
            ->select($select)
            ->join(QuoteItem::FIELD_PRODUCT);

        $this->applyCategory($queryBuilder);

        return $queryBuilder
            ->leftJoin('creditNote')
            ->where([
                QuoteItem::ATTR_PRODUCT_ID . '!=' => null,
                'creditNote.' . OrderEntity::FIELD_IS_ISSUED => true,
                'creditNote.' . OrderEntity::FIELD_POSTING_DATE . '!=' => null,
                'creditNote.' . OrderEntity::ATTR_AMOUNT_LOCAL_CURRENCY => $currency,
            ])
            ->group(self::EXPR_GROUP)
            ->build();
    }

    /**
     * @return Selection[]
     */
    private function prepareSelect(bool $noSum): array
    {
        $select = [];

        if (!$noSum) {
            $expr = Expr::sum(Expr::column(QuoteItem::FIELD_QUANTITY));
            $select[] = Selection::create($expr, 'sumQuantity');

            $expr = Expr::sum(Expr::column(QuoteItem::FIELD_AMOUNT_LOCAL));
            $select[] = Selection::create($expr, 'sumAmount');
        }

        $select[] = Selection::create(Expr::column(self::EXPR_GROUP), 'group');

        return $select;
    }

    private function applyCategory(SelectBuilder $queryBuilder): void
    {
        $queryBuilder
            ->join(
                Join::createWithTableTarget(ProductCategory::ENTITY_TYPE . 'Path', 'path')
                    ->withConditions(
                        Cond::equal(
                            Expr::column(QuoteItem::FIELD_PRODUCT . '.categoryId'),
                            Expr::column('path.descendorId'),
                        ),
                    )
            )
            ->join(
                Join::createWithTableTarget(ProductCategory::ENTITY_TYPE, self::ALIAS_CATEGORY)
                    ->withConditions(
                        Cond::and(
                            Cond::equal(
                                Expr::column(self::ALIAS_CATEGORY . '.' . Attribute::DELETED),
                                false
                            ),
                            Expr::isNull(
                                Expr::column(self::ALIAS_CATEGORY . '.parentId')
                            ),
                            Cond::equal(
                                Expr::column(self::ALIAS_CATEGORY . '.' . Attribute::ID),
                                Expr::column('path.ascendorId'),
                            ),
                        )
                    )
            );
    }

    private function prepareResult(array $rows, ?Item $where, string $currency): Result
    {
        $groupByAlias = 'productCategoryId';

        $quantityLabel = $this->defaultLanguage->translateLabel('quantity', 'fields', QuoteItem::ENTITY_TYPE);
        $amountLabel = $this->defaultLanguage->translateLabel('amount', 'fields', QuoteItem::ENTITY_TYPE);
        $groupLabel = $this->defaultLanguage->translateLabel(ProductCategory::ENTITY_TYPE, 'scopeNames');

        $data = new ResultData(
            entityType: Invoice::ENTITY_TYPE,
            group: new Group(
                name: $groupByAlias,
                label: $groupLabel,
                valueLabelKey: 'productCategoryName',
            ),
            columns: [
                new Column(
                    name: self::ALIAS_QUANTITY,
                    label: $quantityLabel,
                    fieldType: FieldType::FLOAT,
                ),
                new Column(
                    name: self::ALIAS_AMOUNT,
                    label: $amountLabel,
                    fieldType: FieldType::CURRENCY,
                ),
            ],
            currency: $currency,
            switchItems: [
                new SwitchItem(
                    name: self::SWITCH_INVOICES,
                    label: $this->defaultLanguage->translateLabel(InvoiceItem::ENTITY_TYPE, 'scopeNamesPlural'),
                    entityType: InvoiceItem::ENTITY_TYPE,
                ),
                new SwitchItem(
                    name: self::SWITCH_CREDIT_NOTES,
                    label: $this->defaultLanguage->translateLabel(CreditNoteItem::ENTITY_TYPE, 'scopeNamesPlural'),
                    entityType: CreditNoteItem::ENTITY_TYPE,
                ),
            ],
        );

        return $this->resultPreparator->prepare($data, Rows::fromAssocList($rows), $where);
    }
}
