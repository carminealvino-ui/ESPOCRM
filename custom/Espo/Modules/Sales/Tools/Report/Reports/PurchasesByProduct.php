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
use Espo\Core\Select\Where\ItemConverterFactory;
use Espo\Core\Utils\Language;
use Espo\Entities\User;
use Espo\Modules\Advanced\Entities\Report;
use Espo\Modules\Advanced\Reports\GridReport;
use Espo\Modules\Advanced\Tools\Report\GridType\Data\Column;
use Espo\Modules\Advanced\Tools\Report\GridType\Data\Group;
use Espo\Modules\Advanced\Tools\Report\GridType\Data\Rows;
use Espo\Modules\Advanced\Tools\Report\GridType\Data\SwitchItem;
use Espo\Modules\Advanced\Tools\Report\GridType\Result;
use Espo\Modules\Advanced\Tools\Report\GridType\ResultData;
use Espo\Modules\Advanced\Tools\Report\GridType\ResultPreparator;
use Espo\Modules\Advanced\Tools\Report\ListType\Result as ListResult;
use Espo\Modules\Advanced\Tools\Report\ListType\SubReportParams;
use Espo\Modules\Sales\Entities\Product;
use Espo\Modules\Sales\Entities\QuoteItem;
use Espo\Modules\Sales\Entities\SupplierBill;
use Espo\Modules\Sales\Entities\SupplierBillItem;
use Espo\Modules\Sales\Entities\SupplierCreditItem;
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
class PurchasesByProduct implements GridReport
{
    private const ALIAS_QUANTITY = 'SUM:quantity';
    private const ALIAS_AMOUNT = 'SUM:amount';

    private const SWITCH_SUPPLIER_BILLS = 'supplierBills';
    private const SWITCH_SUPPLIER_CREDITS = 'supplierCredits';

    public function __construct(
        private EntityManager $entityManager,
        private SelectBuilderFactory $selectBuilderFactory,
        private Report $report,
        private Language $defaultLanguage,
        private ConfigDataProvider $configDataProvider,
        private Helper $helper,
        private ItemConverterFactory $itemConverterFactory,
        private User $user,
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

        $productId = $subReportParams->getGroupValue();

        if (!$productId) {
            throw new BadRequest("No group value.");
        }

        $where = $searchParams->getWhere();

        if ($subReportParams->getTarget() === self::SWITCH_SUPPLIER_BILLS || !$subReportParams->getTarget()) {
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
                ->from(SupplierBillItem::ENTITY_TYPE)
                ->forUser($user)
                ->withSearchParams($searchParams)
                ->withStrictAccessControl()
                ->buildQueryBuilder();

            $this->applyCategory($queryBuilder);

            $query = $queryBuilder
                ->where([
                    'supplierBill.' . OrderEntity::FIELD_IS_ISSUED => true,
                    'supplierBill.' . OrderEntity::FIELD_POSTING_DATE . '!=' => null,
                    'supplierBill.' . OrderEntity::ATTR_AMOUNT_LOCAL_CURRENCY => $currency,
                    QuoteItem::ATTR_PRODUCT_ID => $productId,
                ])
                ->build();

            $collection = $this->entityManager->getRDBRepositoryByClass(SupplierBillItem::class)->clone($query)->find();
            $total = $this->entityManager->getRDBRepositoryByClass(SupplierBillItem::class)->clone($query)->count();
        } else if ($subReportParams->getTarget() === self::SWITCH_SUPPLIER_CREDITS) {
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
                ->from(SupplierCreditItem::ENTITY_TYPE)
                ->forUser($user)
                ->withSearchParams($searchParams)
                ->withStrictAccessControl()
                ->buildQueryBuilder();

            $this->applyCategory($queryBuilder);

            $query = $queryBuilder
                ->where([
                    'supplierCredit.' . OrderEntity::FIELD_IS_ISSUED => true,
                    'supplierCredit.' . OrderEntity::FIELD_POSTING_DATE . '!=' => null,
                    'supplierCredit.' . OrderEntity::ATTR_AMOUNT_LOCAL_CURRENCY => $currency,
                    QuoteItem::ATTR_PRODUCT_ID => $productId,
                ])
                ->build();

            $collection = $this->entityManager->getRDBRepositoryByClass(SupplierCreditItem::class)->clone($query)->find();
            $total = $this->entityManager->getRDBRepositoryByClass(SupplierCreditItem::class)->clone($query)->count();
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
            ($this->report->getInternalParams()->orderByField ?? null) === 'product'
        ) {
            $orderByExpr = Expr::column('product.name');
            $orderDirection = Order::ASC;
        }

        $maxSize = $this->report->getInternalParams()->maxSize ?? 0;

        return SelectBuilder::create()
            ->select([
                Selection::create($quantityExpression, self::ALIAS_QUANTITY),
                Selection::create($amountExpression, self::ALIAS_AMOUNT),
                Selection::create($groupExpression, QuoteItem::ATTR_PRODUCT_ID),
                Selection::create(Expr::column('product.name'), 'productName'),
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
                Join::createWithTableTarget(Product::ENTITY_TYPE, 'product')
                    ->withConditions(
                        Cond::and(
                            Expr::equal(
                                Expr::column('product.id'),
                                Expr::column('k.group'),
                            ),
                            Expr::equal(
                                Expr::column('product.' . Attribute::DELETED),
                                false,
                            )
                        )
                    )
            )
            ->order($orderByExpr, $orderDirection)
            ->limit(0, $maxSize)
            ->build();
    }

    private function prepareInvoiceQuery(?Item $where, string $currency, bool $noSum = false): Select
    {
        $builder = $this->selectBuilderFactory
            ->create()
            ->from(SupplierBillItem::ENTITY_TYPE);

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

        $this->applyCategory($queryBuilder);

        $select = $this->prepareSelect($noSum);

        return $queryBuilder
            ->select($select)
            ->leftJoin('supplierBill')
            ->where([
                QuoteItem::ATTR_PRODUCT_ID . '!=' => null,
                'supplierBill.' . OrderEntity::FIELD_IS_ISSUED => true,
                'supplierBill.' . OrderEntity::FIELD_POSTING_DATE . '!=' => null,
                'supplierBill.' . OrderEntity::ATTR_AMOUNT_LOCAL_CURRENCY => $currency,
            ])
            ->group(QuoteItem::ATTR_PRODUCT_ID)
            ->build();
    }

    private function prepareCreditNoteQuery(?Item $where, string $currency, bool $noSum = false): Select
    {
        $builder = $this->selectBuilderFactory
            ->create()
            ->from(SupplierCreditItem::ENTITY_TYPE);

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

        $this->applyCategory($queryBuilder);

        $select = $this->prepareSelect($noSum);

        return $queryBuilder
            ->select($select)
            ->leftJoin('supplierCredit')
            ->where([
                QuoteItem::ATTR_PRODUCT_ID . '!=' => null,
                'supplierCredit.' . OrderEntity::FIELD_IS_ISSUED => true,
                'supplierCredit.' . OrderEntity::FIELD_POSTING_DATE . '!=' => null,
                'supplierCredit.' . OrderEntity::ATTR_AMOUNT_LOCAL_CURRENCY => $currency,
            ])
            ->group(QuoteItem::ATTR_PRODUCT_ID)
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

        $select[] = Selection::create(Expr::create(QuoteItem::ATTR_PRODUCT_ID), 'group');

        return $select;
    }

    private function prepareResult(array $rows, ?Item $where, string $currency): Result
    {
        $groupByAlias = QuoteItem::ATTR_PRODUCT_ID;

        $quantityLabel = $this->defaultLanguage->translateLabel('quantity', 'fields', QuoteItem::ENTITY_TYPE);
        $amountLabel = $this->defaultLanguage->translateLabel('amount', 'fields', QuoteItem::ENTITY_TYPE);
        $groupLabel = $this->defaultLanguage->translateLabel(Product::ENTITY_TYPE, 'scopeNames');

        $data = new ResultData(
            entityType: SupplierBill::ENTITY_TYPE,
            group: new Group(
                name: $groupByAlias,
                label: $groupLabel,
                valueLabelKey: 'productName',
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
                    name: self::SWITCH_SUPPLIER_BILLS,
                    label: $this->defaultLanguage->translateLabel(SupplierBillItem::ENTITY_TYPE, 'scopeNamesPlural'),
                    entityType: SupplierBillItem::ENTITY_TYPE,
                ),
                new SwitchItem(
                    name: self::SWITCH_SUPPLIER_CREDITS,
                    label: $this->defaultLanguage->translateLabel(SupplierCreditItem::ENTITY_TYPE, 'scopeNamesPlural'),
                    entityType: SupplierCreditItem::ENTITY_TYPE,
                ),
            ],
        );

        return $this->resultPreparator->prepare($data, Rows::fromAssocList($rows), $where);
    }

    private function applyCategory(SelectBuilder $queryBuilder): void
    {
        $categoryId = $this->report->getInternalParams()->productCategoryId ?? null;

        if (!$categoryId) {
            return;
        }

        $converter = $this->itemConverterFactory->createForType('inCategory', Product::ENTITY_TYPE, $this->user);

        $sqBuilder = SelectBuilder::create()
            ->from(Product::ENTITY_TYPE)
            ->select(Attribute::ID);

        $item = ItemBuilder::create()
            ->setType('inCategory')
            ->setAttribute('category')
            ->setValue($categoryId)
            ->build();

        try {
            $converter->convert($sqBuilder, $item);
        } catch (BadRequest $e) {
            throw new RuntimeException(previous: $e);
        }

        $queryBuilder->where(
            Cond::in(
                Expr::column(QuoteItem::ATTR_PRODUCT_ID),
                $sqBuilder->build(),
            )
        );
    }
}

