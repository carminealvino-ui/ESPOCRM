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
use Espo\Core\Field\Date;
use Espo\Core\ORM\Type\FieldType;
use Espo\Core\Select\SearchParams;
use Espo\Core\Select\SelectBuilderFactory;
use Espo\Core\Select\Where\Item;
use Espo\Core\Select\Where\ItemBuilder;
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
use Espo\Modules\Sales\Entities\PaymentAllocation;
use Espo\Modules\Sales\Entities\PaymentEntry;
use Espo\Modules\Sales\Entities\WriteOffEntry;
use Espo\Modules\Advanced\Tools\Report\GridType\Data\Column;
use Espo\Modules\Advanced\Tools\Report\GridType\Data\Group;
use Espo\Modules\Advanced\Tools\Report\GridType\ResultData;
use Espo\Modules\Advanced\Tools\Report\GridType\ResultPreparator;
use Espo\Modules\Advanced\Tools\Report\GridType\Data\SwitchItem;
use Espo\Modules\Sales\Tools\Payment\PartyType;
use Espo\Modules\Sales\Tools\Report\Helper\CustomerQueryHelper;
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
use Exception;
use PDO;

/**
 * @noinspection PhpUnused
 */
class CustomerBalances implements GridReport
{
    private const ALIAS_AMOUNT_OPENING = 'SUM:amountOpening';
    private const ALIAS_AMOUNT_CLOSING = 'SUM:amountClosing';
    private const ALIAS_DEBITS = 'SUM:debits';
    private const ALIAS_CREDITS = 'SUM:credits';

    private const SWITCH_INVOICES = 'invoices';
    private const SWITCH_CREDIT_NOTES = 'creditNotes';
    private const SWITCH_PAYMENT_ENTRIES = 'paymentEntries';
    private const SWITCH_PAYMENT_ALLOCATIONS = 'paymentAllocations';

    public function __construct(
        private EntityManager $entityManager,
        private SelectBuilderFactory $selectBuilderFactory,
        private Report $report,
        private Language $defaultLanguage,
        private ConfigDataProvider $configDataProvider,
        private Helper $helper,
        private ResultPreparator $resultPreparator,
        private CustomerQueryHelper $customerQueryHelper,
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

        if ($subReportParams->getTarget() === self::SWITCH_INVOICES) {
            $entityType = Invoice::ENTITY_TYPE;

            $where = $searchParams->getWhere();

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
                ->from(Invoice::ENTITY_TYPE)
                ->forUser($user)
                ->withSearchParams($searchParams)
                ->withStrictAccessControl()
                ->buildQueryBuilder();

            $query = $queryBuilder
                ->where([
                    OrderEntity::ATTR_AMOUNT_LOCAL_CURRENCY => $currency,
                    OrderEntity::FIELD_IS_ISSUED => true,
                    OrderEntity::ATTR_ACCOUNT_ID => $accountId,
                ])
                ->build();
        } else if ($subReportParams->getTarget() === self::SWITCH_CREDIT_NOTES) {
            $entityType = CreditNote::ENTITY_TYPE;

            $where = $searchParams->getWhere();

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
                ->from(CreditNote::ENTITY_TYPE)
                ->forUser($user)
                ->withSearchParams($searchParams)
                ->withStrictAccessControl()
                ->buildQueryBuilder();

            $query = $queryBuilder
                ->where([
                    OrderEntity::ATTR_AMOUNT_LOCAL_CURRENCY => $currency,
                    OrderEntity::FIELD_IS_ISSUED => true,
                    OrderEntity::ATTR_ACCOUNT_ID => $accountId,
                ])
                ->build();
        } else if ($subReportParams->getTarget() === self::SWITCH_PAYMENT_ENTRIES) {
            $entityType = PaymentEntry::ENTITY_TYPE;

            $where = $searchParams->getWhere();

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
                ->from(PaymentEntry::ENTITY_TYPE)
                ->forUser($user)
                ->withSearchParams($searchParams)
                ->withStrictAccessControl()
                ->buildQueryBuilder();

            $query = $queryBuilder
                ->where([
                    PaymentEntry::FIELD_PARTY_TYPE => PartyType::Customer->value,
                    OrderEntity::ATTR_AMOUNT_LOCAL_CURRENCY => $currency,
                    OrderEntity::FIELD_IS_ISSUED => true,
                    OrderEntity::ATTR_ACCOUNT_ID => $accountId,
                    OrderEntity::FIELD_STATUS . '!=' => PaymentEntry::STATUS_CANCELED,
                ])
                ->build();
        } else if ($subReportParams->getTarget() === self::SWITCH_PAYMENT_ALLOCATIONS) {
            $entityType = PaymentAllocation::ENTITY_TYPE;

            $where = $searchParams->getWhere();

            if ($where) {
                $where = $this->helper->changeWhereItem(
                    item: $where,
                    from: OrderEntity::FIELD_POSTING_DATE,
                    to: PaymentAllocation::FIELD_DATE,
                );

                $where = $this->excludeAccountFromWhere($where);

                $searchParams = $searchParams->withWhere($where);
            }

            $queryBuilder = $this->selectBuilderFactory
                ->create()
                ->from(PaymentAllocation::ENTITY_TYPE)
                ->forUser($user)
                ->withSearchParams($searchParams)
                ->withWherePermissionCheck()
                ->withComplexExpressionsForbidden()
                ->buildQueryBuilder();

            $queryBuilder
                ->leftJoin(PaymentAllocation::LINK_CREDIT_NOTE)
                ->leftJoin(PaymentAllocation::LINK_PAYMENT_ENTRY)
                ->leftJoin(PaymentAllocation::LINK_WRITE_OFF);

            $query = $queryBuilder
                ->where([
                    OrderEntity::ATTR_AMOUNT_LOCAL_CURRENCY => $currency,
                    'OR' => [
                        PaymentAllocation::LINK_CREDIT_NOTE . '.' . OrderEntity::ATTR_ACCOUNT_ID => $accountId,
                        [
                            PaymentAllocation::LINK_PAYMENT_ENTRY . '.' . OrderEntity::ATTR_ACCOUNT_ID => $accountId,
                            PaymentAllocation::LINK_PAYMENT_ENTRY . '.' . PaymentEntry::FIELD_PARTY_TYPE =>
                                PartyType::Customer->value,
                        ],
                        PaymentAllocation::LINK_WRITE_OFF . '.' . OrderEntity::ATTR_ACCOUNT_ID => $accountId,
                    ]
                ])
                ->build();
        } else {
            throw new BadRequest();
        }

        $collection = $this->entityManager->getRDBRepository($entityType)->clone($query)->find();
        $total = $this->entityManager->getRDBRepository($entityType)->clone($query)->count();

        if ($collection->getEntityType() === PaymentAllocation::ENTITY_TYPE) {
            foreach ($collection as $entity) {
                if ($entity instanceof PaymentAllocation) {
                    $entity->loadParentNameField(PaymentAllocation::LINK_TARGET);
                }
            }
        }

        return new ListResult($collection, $total);
    }

    /**
     * @return array{?Date, ?Date}
     * @throws BadRequest
     */
    public static function obtainRange(?Item $where): array
    {
        if (!$where) {
            return [null, null];
        }

        $items = $where->getItemList();

        $item = null;

        foreach ($items as $it) {
            if ($it->getAttribute() === OrderEntity::FIELD_POSTING_DATE) {
                $item = $it;

                break;
            }
        }

        if (!$item) {
            return [null, null];
        }

        $item = $items[0];

        if ($item->getType() === Item\Type::EVER) {
            return [null, null];
        }

        if ($item->getType() === Item\Type::ON) {
            if (!$item->getValue()) {
                return [null, null];
            }

            try {
                $date = Date::fromString($item->getValue());
            } catch (Exception) {
                throw new BadRequest();
            }

            return [$date, $date];
        }

        if ($item->getType() === Item\Type::BETWEEN) {
            $dates = $item->getValue();

            if (!is_array($dates) || count($dates) === 2) {
                new BadRequest("Bad runtime filter.");
            }

            try {
                return [
                    Date::fromString($dates[0]),
                    Date::fromString($dates[1]),
                ];
            } catch (Exception) {
                throw new BadRequest();
            }
        }

        if ($item->getType() === Item\Type::AFTER) {
            try {
                $date = Date::fromString($item->getValue())->addDays(1);
            } catch (Exception) {
                throw new BadRequest();
            }

            return [$date, null];
        }

        if ($item->getType() === Item\Type::BEFORE) {
            try {
                $date = Date::fromString($item->getValue())->addDays(-1);
            } catch (Exception) {
                throw new BadRequest();
            }

            return [null, $date];
        }

        throw new BadRequest("Not supported runtime filter.");
    }

    /**
     * @throws BadRequest
     */
    private function prepareQuery(?Item $where, string $currency): Select
    {
        [$dateFrom, $dateTo] = $this->obtainRange($where);

        $amountOpeningExpression =
            Expr::subtract(
                Expr::add(
                    Expr::coalesce(Expr::alias('iOpening.sumAmount'), Expr::value(0.0)),
                    Expr::coalesce(Expr::alias('cFxGainOpening.sumAmount'), Expr::value(0.0)),
                    Expr::coalesce(Expr::alias('pFxGainOpening.sumAmount'), Expr::value(0.0)),
                    Expr::coalesce(Expr::alias('poOpening.sumAmount'), Expr::value(0.0)),
                ),
                Expr::coalesce(Expr::alias('cOpening.sumAmount'), Expr::value(0.0)),
                Expr::coalesce(Expr::alias('piOpening.sumAmount'), Expr::value(0.0)),
                Expr::coalesce(Expr::alias('awOpening.sumAmount'), Expr::value(0.0)),
                Expr::coalesce(Expr::alias('cFxLossOpening.sumAmount'), Expr::value(0.0)),
                Expr::coalesce(Expr::alias('pFxLossOpening.sumAmount'), Expr::value(0.0)),
            );

        $amountClosingExpression =
            Expr::subtract(
                Expr::add(
                    Expr::coalesce(Expr::alias('i.sumAmount'), Expr::value(0.0)),
                    Expr::coalesce(Expr::alias('cFxGain.sumAmount'), Expr::value(0.0)),
                    Expr::coalesce(Expr::alias('pFxGain.sumAmount'), Expr::value(0.0)),
                    Expr::coalesce(Expr::alias('po.sumAmount'), Expr::value(0.0)),
                ),
                Expr::coalesce(Expr::alias('c.sumAmount'), Expr::value(0.0)),
                Expr::coalesce(Expr::alias('pi.sumAmount'), Expr::value(0.0)),
                Expr::coalesce(Expr::alias('aw.sumAmount'), Expr::value(0.0)),
                Expr::coalesce(Expr::alias('cFxLoss.sumAmount'), Expr::value(0.0)),
                Expr::coalesce(Expr::alias('pFxLoss.sumAmount'), Expr::value(0.0)),
            );

        $debitsExpression = Expr::add(
            Expr::coalesce(Expr::alias('iMiddle.sumAmount'), Expr::value(0.0)),
            Expr::coalesce(Expr::alias('cFxGainMiddle.sumAmount'), Expr::value(0.0)),
            Expr::coalesce(Expr::alias('pFxGainMiddle.sumAmount'), Expr::value(0.0)),
            Expr::coalesce(Expr::alias('poMiddle.sumAmount'), Expr::value(0.0)),
        );

        $creditsExpression = Expr::add(
            Expr::coalesce(Expr::alias('cMiddle.sumAmount'), Expr::value(0.0)),
            Expr::coalesce(Expr::alias('piMiddle.sumAmount'), Expr::value(0.0)),
            Expr::coalesce(Expr::alias('awMiddle.sumAmount'), Expr::value(0.0)),
            Expr::coalesce(Expr::alias('cFxLossMiddle.sumAmount'), Expr::value(0.0)),
            Expr::coalesce(Expr::alias('pFxLossMiddle.sumAmount'), Expr::value(0.0)),
        );

        $groupExpression = Expr::column('k.group');

        $orderByExpr = $amountClosingExpression;
        $orderDirection = Order::DESC;

        if (
            ($this->report->getInternalParams()->orderByField ?? null) === 'account'
        ) {
            $orderByExpr = Expr::column('account.name');
            $orderDirection = Order::ASC;
        }

        $maxSize = $this->report->getInternalParams()->maxSize ?? 0;

        $selectBuilder = SelectBuilder::create()
            ->select([
                Selection::create($amountOpeningExpression, self::ALIAS_AMOUNT_OPENING),
                Selection::create($debitsExpression, self::ALIAS_DEBITS),
                Selection::create($creditsExpression, self::ALIAS_CREDITS),
                Selection::create($amountClosingExpression, self::ALIAS_AMOUNT_CLOSING),
                Selection::create($groupExpression, OrderEntity::ATTR_ACCOUNT_ID),
                Selection::create(Expr::column('account.name'), 'accountName'),
            ])
            ->fromQuery(
                UnionBuilder::create()
                    ->query(
                        $this->customerQueryHelper->prepareSubQuery(
                            entityType: Invoice::ENTITY_TYPE,
                            where: $where,
                            currency: $currency,
                            dateTo: $dateTo,
                            isClosing: true,
                            noSum: true,
                        ),
                    )
                    ->query(
                        $this->customerQueryHelper->prepareSubQuery(
                            entityType: CreditNote::ENTITY_TYPE,
                            where: $where,
                            currency: $currency,
                            dateTo: $dateTo,
                            isClosing: true,
                            noSum: true,
                        ),
                    )
                    ->query(
                        $this->customerQueryHelper->prepareSubQuery(
                            entityType: PaymentEntry::ENTITY_TYPE,
                            where: $where,
                            currency: $currency,
                            dateTo: $dateTo,
                            isClosing: true,
                            noSum: true,
                        ),
                    )
                    ->query(
                        $this->customerQueryHelper->prepareSubQuery(
                            entityType: PaymentAllocation::ENTITY_TYPE,
                            where: $where,
                            currency: $currency,
                            dateTo: $dateTo,
                            isClosing: true,
                            noSum: true,
                            sourceType: CreditNote::ENTITY_TYPE,
                        ),
                    )
                    ->query(
                        $this->customerQueryHelper->prepareSubQuery(
                            entityType: PaymentAllocation::ENTITY_TYPE,
                            where: $where,
                            currency: $currency,
                            dateTo: $dateTo,
                            isClosing: true,
                            noSum: true,
                            sourceType: PaymentEntry::ENTITY_TYPE,
                        ),
                    )
                    ->query(
                        $this->customerQueryHelper->prepareSubQuery(
                            entityType: PaymentAllocation::ENTITY_TYPE,
                            where: $where,
                            currency: $currency,
                            dateTo: $dateTo,
                            isClosing: true,
                            noSum: true,
                            sourceType: WriteOffEntry::ENTITY_TYPE,
                        ),
                    )
                    ->build(),
                'k'
            )
            ->leftJoin(
                Join
                    ::createWithSubQuery(
                        $this->customerQueryHelper->prepareSubQuery(
                            entityType: Invoice::ENTITY_TYPE,
                            where: $where,
                            currency: $currency,
                            dateTo: $dateTo,
                            isClosing: true,
                        ),
                        'i'
                    )
                    ->withConditions(
                        Expr::equal(Expr::column('k.group'), Expr::column('i.group'))
                    )
            )
            ->leftJoin(
                Join
                    ::createWithSubQuery(
                        $this->customerQueryHelper->prepareSubQuery(
                            entityType: CreditNote::ENTITY_TYPE,
                            where: $where,
                            currency: $currency,
                            dateTo: $dateTo,
                            isClosing: true,
                        ),
                        'c'
                    )
                    ->withConditions(
                        Expr::equal(Expr::column('k.group'), Expr::column('c.group'))
                    )
            )
            ->leftJoin(
                Join
                    ::createWithSubQuery(
                        $this->customerQueryHelper->prepareSubQuery(
                            entityType: PaymentEntry::ENTITY_TYPE,
                            where: $where,
                            currency: $currency,
                            dateTo: $dateTo,
                            isClosing: true,
                            isOutbound: false,
                        ),
                        'pi'
                    )
                    ->withConditions(
                        Expr::equal(Expr::column('k.group'), Expr::column('pi.group'))
                    )
            )
            ->leftJoin(
                Join
                    ::createWithSubQuery(
                        $this->customerQueryHelper->prepareSubQuery(
                            entityType: PaymentEntry::ENTITY_TYPE,
                            where: $where,
                            currency: $currency,
                            dateTo: $dateTo,
                            isClosing: true,
                            isOutbound: true,
                        ),
                        'po'
                    )
                    ->withConditions(
                        Expr::equal(Expr::column('k.group'), Expr::column('po.group'))
                    )
            )
            ->leftJoin(
                Join
                    ::createWithSubQuery(
                        $this->customerQueryHelper->prepareSubQuery(
                            entityType: PaymentAllocation::ENTITY_TYPE,
                            where: $where,
                            currency: $currency,
                            dateTo: $dateTo,
                            isClosing: true,
                            sourceType: CreditNote::ENTITY_TYPE,
                        ),
                        'cFxGain'
                    )
                    ->withConditions(
                        Expr::equal(Expr::column('k.group'), Expr::column('cFxGain.group'))
                    )
            )
            ->leftJoin(
                Join
                    ::createWithSubQuery(
                        $this->customerQueryHelper->prepareSubQuery(
                            entityType: PaymentAllocation::ENTITY_TYPE,
                            where: $where,
                            currency: $currency,
                            dateTo: $dateTo,
                            isClosing: true,
                            sourceType: PaymentEntry::ENTITY_TYPE,
                        ),
                        'pFxGain'
                    )
                    ->withConditions(
                        Expr::equal(Expr::column('k.group'), Expr::column('pFxGain.group'))
                    )
            )
            ->leftJoin(
                Join
                    ::createWithSubQuery(
                        $this->customerQueryHelper->prepareSubQuery(
                            entityType: PaymentAllocation::ENTITY_TYPE,
                            where: $where,
                            currency: $currency,
                            dateTo: $dateTo,
                            isClosing: true,
                            sourceType: CreditNote::ENTITY_TYPE,
                            isLoss: true,
                        ),
                        'cFxLoss'
                    )
                    ->withConditions(
                        Expr::equal(Expr::column('k.group'), Expr::column('cFxLoss.group'))
                    )
            )
            ->leftJoin(
                Join
                    ::createWithSubQuery(
                        $this->customerQueryHelper->prepareSubQuery(
                            entityType: PaymentAllocation::ENTITY_TYPE,
                            where: $where,
                            currency: $currency,
                            dateTo: $dateTo,
                            isClosing: true,
                            sourceType: PaymentEntry::ENTITY_TYPE,
                            isLoss: true,
                        ),
                        'pFxLoss'
                    )
                    ->withConditions(
                        Expr::equal(Expr::column('k.group'), Expr::column('pFxLoss.group'))
                    )
            )
            ->leftJoin(
                Join
                    ::createWithSubQuery(
                        $this->customerQueryHelper->prepareSubQuery(
                            entityType: PaymentAllocation::ENTITY_TYPE,
                            where: $where,
                            currency: $currency,
                            dateTo: $dateTo,
                            isClosing: true,
                            sourceType: WriteOffEntry::ENTITY_TYPE,
                        ),
                        'aw'
                    )
                    ->withConditions(
                        Expr::equal(Expr::column('k.group'), Expr::column('aw.group'))
                    )
            )
            // ---
            ->leftJoin(
                Join
                    ::createWithSubQuery(
                        $this->customerQueryHelper->prepareSubQuery(
                            entityType: Invoice::ENTITY_TYPE,
                            where: $where,
                            currency: $currency,
                            dateFrom: $dateFrom,
                            isOpening: true,
                        ),
                        'iOpening'
                    )
                    ->withConditions(
                        Expr::equal(Expr::column('k.group'), Expr::column('iOpening.group'))
                    )
            )
            ->leftJoin(
                Join
                    ::createWithSubQuery(
                        $this->customerQueryHelper->prepareSubQuery(
                            entityType: CreditNote::ENTITY_TYPE,
                            where: $where,
                            currency: $currency,
                            dateFrom: $dateFrom,
                            isOpening: true,
                        ),
                        'cOpening'
                    )
                    ->withConditions(
                        Expr::equal(Expr::column('k.group'), Expr::column('cOpening.group'))
                    )
            )
            ->leftJoin(
                Join
                    ::createWithSubQuery(
                        $this->customerQueryHelper->prepareSubQuery(
                            entityType: PaymentEntry::ENTITY_TYPE,
                            where: $where,
                            currency: $currency,
                            dateFrom: $dateFrom,
                            isOpening: true,
                            isOutbound: false,
                        ),
                        'piOpening'
                    )
                    ->withConditions(
                        Expr::equal(Expr::column('k.group'), Expr::column('piOpening.group'))
                    )
            )
            ->leftJoin(
                Join
                    ::createWithSubQuery(
                        $this->customerQueryHelper->prepareSubQuery(
                            entityType: PaymentEntry::ENTITY_TYPE,
                            where: $where,
                            currency: $currency,
                            dateFrom: $dateFrom,
                            isOpening: true,
                            isOutbound: true,
                        ),
                        'poOpening'
                    )
                    ->withConditions(
                        Expr::equal(Expr::column('k.group'), Expr::column('poOpening.group'))
                    )
            )
            ->leftJoin(
                Join
                    ::createWithSubQuery(
                        $this->customerQueryHelper->prepareSubQuery(
                            entityType: PaymentAllocation::ENTITY_TYPE,
                            where: $where,
                            currency: $currency,
                            dateFrom: $dateFrom,
                            isOpening: true,
                            sourceType: CreditNote::ENTITY_TYPE
                        ),
                        'cFxGainOpening'
                    )
                    ->withConditions(
                        Expr::equal(Expr::column('k.group'), Expr::column('cFxGainOpening.group'))
                    )
            )
            ->leftJoin(
                Join
                    ::createWithSubQuery(
                        $this->customerQueryHelper->prepareSubQuery(
                            entityType: PaymentAllocation::ENTITY_TYPE,
                            where: $where,
                            currency: $currency,
                            dateFrom: $dateFrom,
                            isOpening: true,
                            sourceType: PaymentEntry::ENTITY_TYPE
                        ),
                        'pFxGainOpening'
                    )
                    ->withConditions(
                        Expr::equal(Expr::column('k.group'), Expr::column('pFxGainOpening.group'))
                    )
            )
            ->leftJoin(
                Join
                    ::createWithSubQuery(
                        $this->customerQueryHelper->prepareSubQuery(
                            entityType: PaymentAllocation::ENTITY_TYPE,
                            where: $where,
                            currency: $currency,
                            dateFrom: $dateFrom,
                            isOpening: true,
                            sourceType: CreditNote::ENTITY_TYPE,
                            isLoss: true,
                        ),
                        'cFxLossOpening'
                    )
                    ->withConditions(
                        Expr::equal(Expr::column('k.group'), Expr::column('cFxLossOpening.group'))
                    )
            )
            ->leftJoin(
                Join
                    ::createWithSubQuery(
                        $this->customerQueryHelper->prepareSubQuery(
                            entityType: PaymentAllocation::ENTITY_TYPE,
                            where: $where,
                            currency: $currency,
                            dateFrom: $dateFrom,
                            isOpening: true,
                            sourceType: PaymentEntry::ENTITY_TYPE,
                            isLoss: true,
                        ),
                        'pFxLossOpening'
                    )
                    ->withConditions(
                        Expr::equal(Expr::column('k.group'), Expr::column('pFxLossOpening.group'))
                    )
            )
            ->leftJoin(
                Join
                    ::createWithSubQuery(
                        $this->customerQueryHelper->prepareSubQuery(
                            entityType: PaymentAllocation::ENTITY_TYPE,
                            where: $where,
                            currency: $currency,
                            dateFrom: $dateFrom,
                            isOpening: true,
                            sourceType: WriteOffEntry::ENTITY_TYPE
                        ),
                        'awOpening'
                    )
                    ->withConditions(
                        Expr::equal(Expr::column('k.group'), Expr::column('awOpening.group'))
                    )
            )
            // ---
            ->leftJoin(
                Join
                    ::createWithSubQuery(
                        $this->customerQueryHelper->prepareSubQuery(
                            entityType: Invoice::ENTITY_TYPE,
                            where: $where,
                            currency: $currency,
                            dateFrom: $dateFrom,
                            dateTo: $dateTo,
                            isMiddle: true,
                        ),
                        'iMiddle'
                    )
                    ->withConditions(
                        Expr::equal(Expr::column('k.group'), Expr::column('iMiddle.group'))
                    )
            )
            ->leftJoin(
                Join
                    ::createWithSubQuery(
                        $this->customerQueryHelper->prepareSubQuery(
                            entityType: CreditNote::ENTITY_TYPE,
                            where: $where,
                            currency: $currency,
                            dateFrom: $dateFrom,
                            dateTo: $dateTo,
                            isMiddle: true,
                        ),
                        'cMiddle'
                    )
                    ->withConditions(
                        Expr::equal(Expr::column('k.group'), Expr::column('cMiddle.group'))
                    )
            )
            ->leftJoin(
                Join
                    ::createWithSubQuery(
                        $this->customerQueryHelper->prepareSubQuery(
                            entityType: PaymentEntry::ENTITY_TYPE,
                            where: $where,
                            currency: $currency,
                            dateFrom: $dateFrom,
                            dateTo: $dateTo,
                            isMiddle: true,
                            isOutbound: false,
                        ),
                        'piMiddle'
                    )
                    ->withConditions(
                        Expr::equal(Expr::column('k.group'), Expr::column('piMiddle.group'))
                    )
            )
            ->leftJoin(
                Join
                    ::createWithSubQuery(
                        $this->customerQueryHelper->prepareSubQuery(
                            entityType: PaymentEntry::ENTITY_TYPE,
                            where: $where,
                            currency: $currency,
                            dateFrom: $dateFrom,
                            dateTo: $dateTo,
                            isMiddle: true,
                            isOutbound: true,
                        ),
                        'poMiddle'
                    )
                    ->withConditions(
                        Expr::equal(Expr::column('k.group'), Expr::column('poMiddle.group'))
                    )
            )
            ->leftJoin(
                Join
                    ::createWithSubQuery(
                        $this->customerQueryHelper->prepareSubQuery(
                            entityType: PaymentAllocation::ENTITY_TYPE,
                            where: $where,
                            currency: $currency,
                            dateFrom: $dateFrom,
                            dateTo: $dateTo,
                            isMiddle: true,
                            sourceType: CreditNote::ENTITY_TYPE
                        ),
                        'cFxGainMiddle'
                    )
                    ->withConditions(
                        Expr::equal(Expr::column('k.group'), Expr::column('cFxGainMiddle.group'))
                    )
            )
            ->leftJoin(
                Join
                    ::createWithSubQuery(
                        $this->customerQueryHelper->prepareSubQuery(
                            entityType: PaymentAllocation::ENTITY_TYPE,
                            where: $where,
                            currency: $currency,
                            dateFrom: $dateFrom,
                            dateTo: $dateTo,
                            isMiddle: true,
                            sourceType: PaymentEntry::ENTITY_TYPE
                        ),
                        'pFxGainMiddle'
                    )
                    ->withConditions(
                        Expr::equal(Expr::column('k.group'), Expr::column('pFxGainMiddle.group'))
                    )
            )
            ->leftJoin(
                Join
                    ::createWithSubQuery(
                        $this->customerQueryHelper->prepareSubQuery(
                            entityType: PaymentAllocation::ENTITY_TYPE,
                            where: $where,
                            currency: $currency,
                            dateFrom: $dateFrom,
                            dateTo: $dateTo,
                            isMiddle: true,
                            sourceType: CreditNote::ENTITY_TYPE,
                            isLoss: true,
                        ),
                        'cFxLossMiddle'
                    )
                    ->withConditions(
                        Expr::equal(Expr::column('k.group'), Expr::column('cFxLossMiddle.group'))
                    )
            )
            ->leftJoin(
                Join
                    ::createWithSubQuery(
                        $this->customerQueryHelper->prepareSubQuery(
                            entityType: PaymentAllocation::ENTITY_TYPE,
                            where: $where,
                            currency: $currency,
                            dateFrom: $dateFrom,
                            dateTo: $dateTo,
                            isMiddle: true,
                            sourceType: PaymentEntry::ENTITY_TYPE,
                            isLoss: true,
                        ),
                        'pFxLossMiddle'
                    )
                    ->withConditions(
                        Expr::equal(Expr::column('k.group'), Expr::column('pFxLossMiddle.group'))
                    )
            )
            ->leftJoin(
                Join
                    ::createWithSubQuery(
                        $this->customerQueryHelper->prepareSubQuery(
                            entityType: PaymentAllocation::ENTITY_TYPE,
                            where: $where,
                            currency: $currency,
                            dateFrom: $dateFrom,
                            dateTo: $dateTo,
                            isMiddle: true,
                            sourceType: WriteOffEntry::ENTITY_TYPE
                        ),
                        'awMiddle'
                    )
                    ->withConditions(
                        Expr::equal(Expr::column('k.group'), Expr::column('awMiddle.group'))
                    )
            )
            // ---
            ->leftJoin(
                Join::createWithTableTarget(Account::ENTITY_TYPE, 'account')
                    ->withConditions(
                        Cond::and(
                            Expr::equal(
                                Expr::column('account.' . Attribute::ID),
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
            ->limit(0, $maxSize);

        if (!$this->includeZero()) {
            $selectBuilder
                ->where(
                    Cond::or(
                        Cond::and(
                            Cond::notEqual($amountOpeningExpression, Expr::value('0')),
                            Expr::isNotNull($amountOpeningExpression)
                        ),
                        Cond::and(
                            Cond::notEqual($amountClosingExpression, Expr::value('0')),
                            Expr::isNotNull($amountClosingExpression)
                        ),
                    )
                );
        }

        return $selectBuilder->build();
    }

    private function excludeAccountFromWhere(Item $where): Item
    {
        $itemList = $where->getItemList();

        $itemList = array_filter($itemList, fn ($it) => $it->getAttribute() !== 'accountId');
        $itemList = array_values($itemList);

        return ItemBuilder::create()
            ->setType($where->getType())
            ->setItemList($itemList)
            ->build();
    }

    private function prepareResult(array $rows, ?Item $where, string $currency): Result
    {
        $groupByAlias = OrderEntity::ATTR_ACCOUNT_ID;
        $columnOpeningLabel = $this->defaultLanguage
            ->translateLabel('amountOpening', 'salesReportColumns', Report::ENTITY_TYPE);
        $columnClosingLabel = $this->defaultLanguage
            ->translateLabel('amountClosing', 'salesReportColumns', Report::ENTITY_TYPE);
        $columnDebitsLabel = $this->defaultLanguage
            ->translateLabel('debits', 'salesReportColumns', Report::ENTITY_TYPE);
        $columnCreditsLabel = $this->defaultLanguage
            ->translateLabel('credits', 'salesReportColumns', Report::ENTITY_TYPE);
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
                    name: self::ALIAS_AMOUNT_OPENING,
                    label: $columnOpeningLabel,
                    fieldType: FieldType::CURRENCY,
                ),
                new Column(
                    name: self::ALIAS_DEBITS,
                    label: $columnDebitsLabel,
                    fieldType: FieldType::CURRENCY,
                ),
                new Column(
                    name: self::ALIAS_CREDITS,
                    label: $columnCreditsLabel,
                    fieldType: FieldType::CURRENCY,
                ),
                new Column(
                    name: self::ALIAS_AMOUNT_CLOSING,
                    label: $columnClosingLabel,
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
                new SwitchItem(
                    name: self::SWITCH_PAYMENT_ENTRIES,
                    label: $this->defaultLanguage->translateLabel(PaymentEntry::ENTITY_TYPE, 'scopeNamesPlural'),
                    entityType: PaymentEntry::ENTITY_TYPE,
                ),
                new SwitchItem(
                    name: self::SWITCH_PAYMENT_ALLOCATIONS,
                    label: $this->defaultLanguage->translateLabel(PaymentAllocation::ENTITY_TYPE, 'scopeNamesPlural'),
                    entityType: PaymentAllocation::ENTITY_TYPE,
                ),
            ],
        );

        return $this->resultPreparator->prepare($data, Rows::fromAssocList($rows), $where);
    }

    private function includeZero(): bool
    {
        return $this->report->getInternalParams()->includeZero ?? false;
    }
}
