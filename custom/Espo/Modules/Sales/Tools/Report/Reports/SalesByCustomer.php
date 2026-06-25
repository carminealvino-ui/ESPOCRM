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
use Espo\Modules\Crm\Entities\Account;
use Espo\Modules\Sales\Entities\CreditNote;
use Espo\Modules\Sales\Entities\Invoice;
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
class SalesByCustomer implements GridReport
{
    private const ALIAS_AMOUNT = 'SUM:amount';

    private const SWITCH_INVOICES = 'invoices';
    private const SWITCH_CREDIT_NOTES = 'creditNotes';

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

        $accountId = $subReportParams->getGroupValue();

        if (!$accountId) {
            throw new BadRequest("No group value.");
        }

        if ($subReportParams->getTarget() === self::SWITCH_INVOICES || !$subReportParams->getTarget()) {
            $where = $searchParams->getWhere();

            if ($where) {
                $where = $this->helper->changeWhereItem(
                    item: $where,
                    from: OrderEntity::FIELD_POSTING_DATE,
                    to: OrderEntity::FIELD_POSTING_DATE,
                );

                $searchParams = $searchParams->withWhere($where);
            }

            $query = $this->selectBuilderFactory
                ->create()
                ->from(Invoice::ENTITY_TYPE)
                ->forUser($user)
                ->withSearchParams($searchParams)
                ->withStrictAccessControl()
                ->buildQueryBuilder()
                ->where([
                    OrderEntity::ATTR_AMOUNT_LOCAL_CURRENCY => $currency,
                    OrderEntity::FIELD_IS_ISSUED => true,
                    OrderEntity::FIELD_POSTING_DATE . '!=' => null,
                    OrderEntity::ATTR_ACCOUNT_ID => $accountId,
                ])
                ->build();

            $collection = $this->entityManager->getRDBRepositoryByClass(Invoice::class)->clone($query)->find();
            $total = $this->entityManager->getRDBRepositoryByClass(Invoice::class)->clone($query)->count();
        } else if ($subReportParams->getTarget() === self::SWITCH_CREDIT_NOTES) {
            $where = $searchParams->getWhere();

            if ($where) {
                $where = $this->helper->changeWhereItem(
                    item: $where,
                    from: OrderEntity::FIELD_POSTING_DATE,
                    to: OrderEntity::FIELD_POSTING_DATE,
                );

                $searchParams = $searchParams->withWhere($where);
            }

            $query = $this->selectBuilderFactory
                ->create()
                ->from(CreditNote::ENTITY_TYPE)
                ->forUser($user)
                ->withSearchParams($searchParams)
                ->withStrictAccessControl()
                ->buildQueryBuilder()
                ->where([
                    OrderEntity::FIELD_IS_ISSUED => true,
                    OrderEntity::FIELD_POSTING_DATE . '!=' => null,
                    OrderEntity::ATTR_AMOUNT_LOCAL_CURRENCY => $currency,
                    OrderEntity::ATTR_ACCOUNT_ID => $accountId,
                ])
                ->build();

            $collection = $this->entityManager->getRDBRepositoryByClass(CreditNote::class)->clone($query)->find();
            $total = $this->entityManager->getRDBRepositoryByClass(CreditNote::class)->clone($query)->count();
        } else {
            throw new RuntimeException();
        }

        return new ListResult($collection, $total);
    }

    private function prepareQuery(?Item $where, string $currency): Select
    {
        $sumExpression =
            Expr::subtract(
                Expr::coalesce(Expr::column('i.sum'), Expr::value(0.0)),
                Expr::coalesce(Expr::column('c.sum'), Expr::value(0.0)),
            );

        $groupExpression = Expr::coalesce(Expr::column('i.group'), Expr::column('c.group'));

        $orderByExpr = $sumExpression;
        $orderDirection = Order::DESC;

        if (
            ($this->report->getInternalParams()->orderByField ?? null) === 'account'
        ) {
            $orderByExpr = Expr::column('account.name');
            $orderDirection = Order::ASC;
        }

        $maxSize = $this->report->getInternalParams()->maxSize ?? 0;

        return SelectBuilder::create()
            ->select([
                Selection::create($sumExpression, self::ALIAS_AMOUNT),
                Selection::create($groupExpression, OrderEntity::ATTR_ACCOUNT_ID),
                Selection::create(Expr::column('account.name'), 'accountName'),
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
                Join::createWithTableTarget(Account::ENTITY_TYPE, 'account')
                    ->withConditions(
                        Cond::and(
                            Expr::equal(
                                Expr::column('account.id'),
                                Expr::column('k.group'),
                            ),
                            Expr::equal(
                                Expr::column('account.' . Attribute::DELETED),
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
            ->from(Invoice::ENTITY_TYPE);

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

        return $queryBuilder
            ->select($select)
            ->where([
                OrderEntity::ATTR_AMOUNT_LOCAL_CURRENCY => $currency,
                OrderEntity::ATTR_ACCOUNT_ID . '!=' => null,
                OrderEntity::FIELD_IS_ISSUED => true,
                OrderEntity::FIELD_POSTING_DATE . '!=' => null,
            ])
            ->group(OrderEntity::ATTR_ACCOUNT_ID)
            ->build();
    }

    private function prepareCreditNoteQuery(?Item $where, string $currency, bool $noSum = false): Select
    {
        $builder = $this->selectBuilderFactory
            ->create()
            ->from(CreditNote::ENTITY_TYPE);

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

        return $queryBuilder
            ->select($select)
            ->where([
                OrderEntity::ATTR_AMOUNT_LOCAL_CURRENCY => $currency,
                OrderEntity::ATTR_ACCOUNT_ID . '!=' => null,
                OrderEntity::FIELD_IS_ISSUED => true,
                OrderEntity::FIELD_POSTING_DATE . '!=' => null,
            ])
            ->group(OrderEntity::ATTR_ACCOUNT_ID)
            ->build();
    }

    /**
     * @return Selection[]
     */
    private function prepareSelect(bool $noSum): array
    {
        $select = [];

        if (!$noSum) {
            $expr = Expr::add(
                Expr::column(OrderEntity::FIELD_AMOUNT_LOCAL),
                Expr::column(OrderEntity::FIELD_ROUNDING_AMOUNT_LOCAL),
            );

            if ($this->report->getInternalParams()->includeShipping ?? false) {
                $expr = Expr::add($expr, Expr::column(OrderEntity::FIELD_SHIPPING_AMOUNT_LOCAL));
            }

            $expr = Expr::sum($expr);

            $select[] = Selection::create($expr, 'sum');
        }

        $select[] = Selection::create(Expr::create(OrderEntity::ATTR_ACCOUNT_ID), 'group');

        return $select;
    }

    private function prepareResult(array $rows, ?Item $where, string $currency): Result
    {
        $groupByAlias = OrderEntity::ATTR_ACCOUNT_ID;
        $columnLabel = $this->defaultLanguage->translateLabel('amount', 'fields', Invoice::ENTITY_TYPE);
        $groupLabel = $this->defaultLanguage->translateLabel(Account::ENTITY_TYPE, 'scopeNames');

        $data = new ResultData(
            entityType: Invoice::ENTITY_TYPE,
            group: new Group(
                name: $groupByAlias,
                label: $groupLabel,
                valueLabelKey: 'accountName',
            ),
            columns: [
                new Column(
                    name: self::ALIAS_AMOUNT,
                    label: $columnLabel,
                    fieldType: FieldType::CURRENCY,
                ),
            ],
            currency: $currency,
            switchItems: [
                new SwitchItem(
                    name: self::SWITCH_INVOICES,
                    label: $this->defaultLanguage->translateLabel(Invoice::ENTITY_TYPE, 'scopeNamesPlural'),
                    entityType: Invoice::ENTITY_TYPE,
                ),
                new SwitchItem(
                    name: self::SWITCH_CREDIT_NOTES,
                    label: $this->defaultLanguage->translateLabel(CreditNote::ENTITY_TYPE, 'scopeNamesPlural'),
                    entityType: CreditNote::ENTITY_TYPE,
                ),
            ],
        );

        return $this->resultPreparator->prepare($data, Rows::fromAssocList($rows), $where);
    }
}
