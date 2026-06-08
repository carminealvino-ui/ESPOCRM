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

use Espo\Core\Currency\CalculatorUtil;
use Espo\Core\Currency\ConfigDataProvider;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Field\Currency;
use Espo\Core\Field\Date;
use Espo\Core\ORM\Type\FieldType;
use Espo\Core\Record\Select\ApplierClassNameListProvider;
use Espo\Core\Select\SearchParams;
use Espo\Core\Select\SelectBuilderFactory;
use Espo\Core\Select\Where\Item;
use Espo\Core\Select\Where\ItemBuilder;
use Espo\Core\Utils\DateTime;
use Espo\Core\Utils\Language;
use Espo\Entities\User;
use Espo\Modules\Advanced\Entities\Report;
use Espo\Modules\Advanced\Reports\GridReport;
use Espo\Modules\Advanced\Tools\Report\GridType\Data\Rows;
use Espo\Modules\Advanced\Tools\Report\GridType\Result;
use Espo\Modules\Advanced\Tools\Report\ListType\Result as ListResult;
use Espo\Modules\Advanced\Tools\Report\ListType\SubReportParams;
use Espo\Modules\Crm\Entities\Account;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Entities\PaymentInstallment;
use Espo\Modules\Sales\Tools\Invoice\InvoiceStatusProvider;
use Espo\Modules\Sales\Tools\Quote\CurrencyConverterUtil;
use Espo\Modules\Advanced\Tools\Report\GridType\Data\Column;
use Espo\Modules\Advanced\Tools\Report\GridType\Data\Group;
use Espo\Modules\Advanced\Tools\Report\GridType\Data\Order;
use Espo\Modules\Advanced\Tools\Report\GridType\Data\OrderDirection;
use Espo\Modules\Advanced\Tools\Report\GridType\ResultData;
use Espo\Modules\Advanced\Tools\Report\GridType\ResultPreparator;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\ORM\EntityCollection;
use Espo\ORM\EntityManager;
use Espo\ORM\Name\Attribute;
use Espo\ORM\Query\Part\Condition;
use Espo\ORM\Query\Part\Expression as Expr;
use Espo\ORM\Query\Part\Join;
use Espo\ORM\Query\SelectBuilder;
use Espo\ORM\SthCollection;
use Exception;
use RuntimeException;

/**
 * @noinspection PhpUnused
 */
class CustomerAging implements GridReport
{
    private const BASIS_DATE_INVOICED = 'dateInvoiced';
    private const BASIS_INSTALLMENT_DATE_DUE = 'installmentDateDue';

    private const COLUMN_AMOUNT = 'SUM:amount';
    private const GROUP = 'group';
    private const GROUP_ACCOUNT_ID = 'accountId';

    private const LIMIT = 5000;

    /** @var array{?int, ?int}[]  */
    private array $buckets = [
        [null, 0],
        [1, 30],
        [31, 60],
        [61, 90],
        [91, null],
    ];

    public function __construct(
        private EntityManager $entityManager,
        private InvoiceStatusProvider $invoiceStatusProvider,
        private SelectBuilderFactory $selectBuilderFactory,
        private Report $report,
        private DateTime $dateTime,
        private ConfigDataProvider $currencyConfig,
        private CurrencyConverterUtil $currencyConverterUtil,
        private ApplierClassNameListProvider $applierClassNameListProvider,
        private ResultPreparator $resultPreparator,
        private Language $defaultLanguage,
    ) {}

    public function run(?Item $where, ?User $user): Result
    {
        $accountIds = $this->obtainAccountIds($where);
        $date = $this->getDate($where);

        $invoices = $this->getInvoices($accountIds);

        /** @var array<string, numeric-string[]> $map */
        $map = [];

        foreach ($invoices as $invoice) {
            $this->processInvoice($invoice, $map, $date);
        }

        // @todo Order by amount.

        $rows = $this->buildRows($map);

        return $this->prepareResult($rows);
    }

    public function runSubReport(SearchParams $searchParams, SubReportParams $subReportParams, ?User $user): ListResult
    {
        $where = $searchParams->getWhere();

        $date = $this->getDate($where);

        $where = $this->excludeDateFromWhere($where);

        $searchParams = $searchParams
            ->withWhere($where)
            ->withWhereAdded(
                ItemBuilder::create()
                    ->setType(Item\Type::GREATER_THAN)
                    ->setAttribute(Invoice::FIELD_AMOUNT_DUE)
                    ->setValue('0')
                    ->build()
            );

        if ($this->isInstallmentInvoicedBasis()) {
            $searchParams = $searchParams
                ->withWhereAdded(
                    ItemBuilder::create()
                        ->setType(Item\Type::GREATER_THAN)
                        ->setAttribute(Invoice::FIELD_OVERDUE_DAYS)
                        ->setValue('0')
                        ->build()
                );
        }

        $queryBuilder = $this->selectBuilderFactory
            ->create()
            ->from(Invoice::ENTITY_TYPE)
            ->forUser($user)
            ->withSearchParams($searchParams)
            ->withStrictAccessControl()
            ->buildQueryBuilder();

        $baseWhere = [
            OrderEntity::ATTR_AMOUNT_LOCAL_CURRENCY => $this->currencyConfig->getBaseCurrency(),
            OrderEntity::FIELD_IS_ISSUED => true,
            OrderEntity::FIELD_STATUS => $this->invoiceStatusProvider->getOpenIssued(),
            OrderEntity::ATTR_ACCOUNT_ID . '!=' => null,
        ];

        if ($this->isDateInvoicedBasis()) {
            $baseWhere[Invoice::FIELD_DATE_INVOICED . '!='] = null;
        } else if (!$this->isInstallmentInvoicedBasis()) {
            $baseWhere[Invoice::FIELD_DATE_DUE . '!='] = null;
        }

        if ($subReportParams->getGroupIndex() === 1) {
            $accountId = $subReportParams->getGroupValue();

            $query = $queryBuilder
                ->where([
                    OrderEntity::ATTR_ACCOUNT_ID => $accountId,
                    ...$baseWhere,
                ])
                ->build();
        } else if ($subReportParams->getGroupIndex() === 0) {
            $bucketValue = $subReportParams->getGroupValue();

            if (!is_string($bucketValue)) {
                throw new BadRequest();
            }

            $baseWhere = $this->prepareSubReportRangeBaseWhere($bucketValue, $baseWhere, $date);

            if ($this->isInstallmentInvoicedBasis()) {
                $queryBuilder->leftJoin(
                    $this->buildInstallmentJoin()
                );
            }

            $query = $queryBuilder
                ->where([
                    ...$baseWhere,
                ])
                ->build();
        } else {
            throw new BadRequest();
        }

        $collection = $this->entityManager->getRDBRepositoryByClass(Invoice::class)->clone($query)->find();
        $total = $this->entityManager->getRDBRepositoryByClass(Invoice::class)->clone($query)->count();

        return new ListResult($collection, $total);
    }

    private function excludeDateFromWhere(Item $where): Item
    {
        $itemList = $where->getItemList();

        $itemList = array_filter($itemList, fn ($it) => $it->getAttribute() !== 'date');
        $itemList = array_values($itemList);

        return ItemBuilder::create()
            ->setType($where->getType())
            ->setItemList($itemList)
            ->build();
    }

    /**
     * @param ?string[] $accountIds
     * @return EntityCollection<Invoice>|SthCollection<Invoice>
     */
    private function getInvoices(?array $accountIds): EntityCollection|SthCollection
    {
        $where = [];

        if ($accountIds !== null) {
            $where[OrderEntity::ATTR_ACCOUNT_ID] = $accountIds;
        }

        $maxSize = $this->report->getInternalClassName()?->maxSize ?? self::LIMIT;

        $searchParams = SearchParams::create()
            ->withSelect(['*'])
            ->withWhereAdded(
                ItemBuilder::create()
                    ->setType(Item\Type::GREATER_THAN)
                    ->setAttribute(Invoice::FIELD_AMOUNT_DUE)
                    ->setValue('0')
                    ->build()
            );

        if ($this->isInstallmentInvoicedBasis()) {
            $searchParams = $searchParams
                ->withWhereAdded(
                    ItemBuilder::create()
                        ->setType(Item\Type::GREATER_THAN)
                        ->setAttribute(Invoice::FIELD_OVERDUE_DAYS)
                        ->setValue('0')
                        ->build()
                );
        }

        try {
            $queryBuilder = $this->selectBuilderFactory
                ->create()
                ->from(Invoice::ENTITY_TYPE)
                ->withSearchParams($searchParams)
                ->buildQueryBuilder();
        } catch (BadRequest|Forbidden $e) {
            throw new RuntimeException(previous: $e);
        }

        if ($this->isDateInvoicedBasis()) {
            $where[Invoice::FIELD_DATE_INVOICED . '!='] = null;
        } else if (!$this->isInstallmentInvoicedBasis()) {
            $where[Invoice::FIELD_DATE_DUE . '!='] = null;
        }

        if ($this->getOrderBy() === 'account') {
            $queryBuilder->order([])
                ->order('accountName');
        }

        /** @var EntityCollection<Invoice> */
        return $this->entityManager
            ->getRDBRepositoryByClass(Invoice::class)
            ->clone($queryBuilder->build())
            ->where([
                OrderEntity::ATTR_AMOUNT_LOCAL_CURRENCY => $this->currencyConfig->getBaseCurrency(),
                OrderEntity::ATTR_ACCOUNT_ID . '!=' => null,
                OrderEntity::FIELD_IS_ISSUED => true,
                OrderEntity::FIELD_STATUS => $this->invoiceStatusProvider->getOpenIssued(),
                ...$where,
            ])
            ->limit(0, $maxSize)
            ->sth()
            ->find();
    }

    /**
     * @return ?string[]
     * @throws BadRequest
     */
    private function obtainAccountIds(?Item $where): ?array
    {
        if (!$where) {
            return null;
        }

        foreach ($where->getItemList() as $item) {
            if (
                $item->getAttribute() !== OrderEntity::ATTR_ACCOUNT_ID ||
                !in_array($item->getType(), [Item\Type::EQUALS, Item\Type::IN])
            ) {
                continue;
            }

            $value = $item->getValue();

            if (is_string($value)) {
                return [$value];
            }

            if (is_array($value)) {
                foreach ($value as $it) {
                    if (!is_string($it)) {
                        throw new BadRequest("Bad where item.");
                    }
                }

                return $value;
            }
        }

        return null;
    }

    private function isDateInvoicedBasis(): bool
    {
        return ($this->report->getInternalParams()->basis ?? null) === self::BASIS_DATE_INVOICED;
    }

    private function isInstallmentInvoicedBasis(): bool
    {
        return ($this->report->getInternalParams()->basis ?? null) === self::BASIS_INSTALLMENT_DATE_DUE;
    }

    /**
     * @param array<string, numeric-string[]> $map
     */
    private function processInvoice(Invoice $invoice, array &$map, Date $base): void
    {
        $date = $this->getInvoiceReferenceDate($invoice);
        $account = $invoice->getAccount();

        if (!$account || !$date) {
            return;
        }

        $days = $this->calculateDays($date, $base);

        $index = $this->getIndex($days);

        if ($index === -1) {
            return;
        }

        $amount = $this->getAmount($invoice, $base);

        if (!$amount) {
            return;
        }

        $vector = $map[$account->getId()] ?? $this->prepareEmptyVector();

        $vector[$index] = CalculatorUtil::add($vector[$index], $amount->getAmountAsString());

        $map[$account->getId()] = $vector;
    }

    /**
     * @throws BadRequest
     */
    private function getDate(?Item $where): Date
    {
        if (!$where) {
            return $this->dateTime->getToday();
        }

        foreach ($where->getItemList() as $item) {
            if (
                $item->getType() !== Item\Type::ON ||
                $item->getAttribute() !== 'date'
            ) {
                continue;
            }

            try {
                return Date::fromString($item->getValue());
            } catch (Exception $e) {
                throw new BadRequest(previous: $e);
            }
        }

        return $this->dateTime->getToday();
    }

    /**
     * @return numeric-string[]
     */
    private function prepareEmptyVector(): array
    {
        $vector = [];

        for ($i = 0; $i < count($this->buckets); $i ++) {
            $vector[] = '0';
        }

        return $vector;
    }

    private function getIndex(int $days): int
    {
        $index = -1;

        foreach ($this->buckets as $i => $bucket) {
            $match = false;

            if ($bucket[0] === null && $bucket[1] !== null) {
                $match = $days <= $bucket[1];
            } else if ($bucket[0] !== null && $bucket[1] === null) {
                $match = $days >= $bucket[0];
            } else if ($bucket[0] !== null && $bucket[1] != null) {
                $match = $days >= $bucket[0] && $days <= $bucket[1];
            }

            if ($match) {
                $index = $i;

                break;
            }
        }

        return $index;
    }

    private function calculateDays(Date $date, Date $base): int
    {
        $diff = $date->diff($base);

        $days = $diff->days;

        if ($days === false) {
            throw new RuntimeException();
        }

        if ($diff->invert) {
            $days = -$days;
        }

        return $days;
    }

    private function getAmount(Invoice $invoice, Date $base): ?Currency
    {
        if ($this->isInstallmentInvoicedBasis()) {
            $amount = $this->getInstallmentAmountDue($invoice, $base);
        } else {
            $amount = $invoice->getAmountDue();
        }

        if (!$amount) {
            return null;
        }

        return $this->convertAmount($invoice, $amount);
    }

    private function getOrderBy(): ?string
    {
        return $this->report->getInternalParams()->orderByField ?? 'amount';
    }

    /**
     * @return string[]
     */
    private function prepareGroups(): array
    {
        $groups = [];

        foreach ($this->buckets as $bucket) {
            $groups[] = $this->bucketToString($bucket);
        }

        return $groups;
    }

    private function getAccountName(string $accountId): string
    {
        $account = $this->entityManager->getRDBRepositoryByClass(Account::class)->getById($accountId);

        return $account?->getName() ?? $accountId;
    }

    /**
     * @param array<string, numeric-string[]> $map
     * @return array<string, mixed>[]
     */
    private function buildRows(array $map): array
    {
        $groups = $this->prepareGroups();

        $rows = [];

        foreach ($map as $accountId => $vector) {
            $accountName = $this->getAccountName($accountId);

            foreach ($groups as $i => $group) {
                $amount = floatval($vector[$i]);

                $row = [
                    self::GROUP_ACCOUNT_ID => $accountId,
                    'accountName' => $accountName,
                    self::GROUP => $group,
                    self::COLUMN_AMOUNT => $amount,
                    'groupLabel' => $this->translateGroupLabel($group),
                ];

                $rows[] = $row;
            }
        }

        if ($this->getOrderBy() === 'account') {
            usort($rows, function ($a, $b) {
                return strcmp($a['accountName'], $b['accountName']);
            });
        }

        return $rows;
    }

    /**
     * @param array<string, mixed>[] $rows
     */
    private function prepareResult(array $rows): Result
    {
        $orders = [];

        if ($this->getOrderBy() === 'amount') {
            $orders[] = new Order(
                column: self::COLUMN_AMOUNT,
                direction: OrderDirection::desc,
            );
        }

        $resultData = new ResultData(
            entityType: Invoice::ENTITY_TYPE,
            group: new Group(
                name: self::GROUP,
                label: $this->defaultLanguage->translateLabel('bucket', 'salesReportColumns', Report::ENTITY_TYPE),
                valueLabelKey: 'groupLabel',
            ),
            columns: [
                new Column(
                    name: self::COLUMN_AMOUNT,
                    label: $this->defaultLanguage->translateLabel('amountDue', 'fields', Invoice::ENTITY_TYPE),
                    fieldType: FieldType::CURRENCY,
                ),
            ],
            secondGroup: new Group(
                name: self::GROUP_ACCOUNT_ID,
                label: $this->defaultLanguage->translateLabel('account', 'fields', Invoice::ENTITY_TYPE),
                valueLabelKey: 'accountName',
            ),
            orders: $orders,
            currency: $this->currencyConfig->getBaseCurrency(),
        );

        return $this->resultPreparator->prepare($resultData, Rows::fromAssocList($rows));
    }

    private function translateGroupLabel(string $group): string
    {
        $label = $group;

        $daysLabel = $this->defaultLanguage->translateLabel('days', 'reportValues', Invoice::ENTITY_TYPE);

        if (str_starts_with($label, '_')) {
            return $this->defaultLanguage->translateLabel('current', 'reportValues', Invoice::ENTITY_TYPE);
        }

        if (str_ends_with($label, '_')) {
            return str_replace('_', '+', $label) . ' ' . $daysLabel;
        }

        return str_replace('_', '-', $label) . ' ' . $daysLabel;
    }

    /**
     * @return array{?int, ?int}
     * @throws BadRequest
     */
    private function getRange(string $bucketValue): mixed
    {
        foreach ($this->buckets as $bucket) {
            if ($this->bucketToString($bucket) === $bucketValue) {
                return $bucket;
            }
        }

        throw new BadRequest();
    }

    /**
     * @param array{?int, ?int} $bucket
     */
    private function bucketToString(array $bucket): string
    {
        return (strval($bucket[0]) ?? '') . '_' . (strval($bucket[1]) ?? '');
    }

    /**
     * @param array<string, mixed> $baseWhere
     * @return array<string, mixed>
     * @throws BadRequest
     */
    private function prepareSubReportRangeBaseWhere(string $bucketValue, array $baseWhere, Date $date): array
    {
        $range = $this->getRange($bucketValue);

        if ($this->isDateInvoicedBasis()) {
            $dateAttribute = Invoice::FIELD_DATE_INVOICED;
        } else if ($this->isInstallmentInvoicedBasis()) {
            $dateAttribute = 'installment.#' . PaymentInstallment::FIELD_DATE;
        } else {
            $dateAttribute = Invoice::FIELD_DATE_DUE;
        }

        if ($range[0] === null && $range[1] !== null) {
            $baseWhere[$dateAttribute . '>='] = $date->addDays(-$range[1])->toString();
        } else if ($range[0] !== null && $range[1] === null) {
            $baseWhere[$dateAttribute . '<='] = $date->addDays(-$range[0])->toString();
        } else if ($range[0] !== null && $range[1] !== null) {
            $baseWhere[$dateAttribute . '<='] = $date->addDays(-$range[0])->toString();
            $baseWhere[$dateAttribute . '>='] = $date->addDays(-$range[1])->toString();
        } else {
            throw new BadRequest();
        }

        return $baseWhere;
    }

    private function buildInstallmentJoin(): Join
    {
        return Join
            ::createWithSubQuery(
                SelectBuilder::create()
                    ->from(PaymentInstallment::ENTITY_TYPE)
                    ->select(
                        Expr::min(
                            Expr::column(PaymentInstallment::FIELD_DATE),
                        ),
                        PaymentInstallment::FIELD_DATE
                    )
                    ->select(PaymentInstallment::FIELD_SOURCE . 'Id', 'sourceId')
                    ->select(PaymentInstallment::FIELD_SOURCE . 'Type', 'sourceType')
                    ->where([
                        PaymentInstallment::FIELD_STATUS . '!=' => PaymentInstallment::STATUS_SETTLED,
                    ])
                    ->group(PaymentInstallment::FIELD_SOURCE . 'Id')
                    ->group(PaymentInstallment::FIELD_SOURCE . 'Type')
                    ->build(),
                'installment'
            )
            ->withConditions(
                Condition::and(
                    Condition::equal(
                        Expr::alias('installment.sourceId'),
                        Expr::column(Attribute::ID)
                    ),
                    Condition::equal(
                        Expr::alias('installment.sourceType'),
                        Invoice::ENTITY_TYPE
                    ),
                )
            );
    }

    private function getInvoiceReferenceDate(Invoice $invoice): ?Date
    {
        if ($this->isDateInvoicedBasis()) {
            return $invoice->getDateInvoiced();
        }

        if (!$this->isInstallmentInvoicedBasis()) {
            return $invoice->getDateDue();
        }

        foreach ($invoice->getInstallmentCollection() as $installment) {
            if ($installment->getStatus() === PaymentInstallment::STATUS_SETTLED) {
                continue;
            }

            return $installment->getDate();
        }

        return null;
    }

    private function convertAmount(Invoice $invoice, Currency $amount): ?Currency
    {
        if ($invoice->getAmountLocal()?->getCode() !== $this->currencyConfig->getBaseCurrency()) {
            return null;
        }

        return $this->currencyConverterUtil->convertToLocal($amount, $invoice);
    }

    private function getInstallmentAmountDue(Invoice $invoice, Date $base): ?Currency
    {
        $due = $invoice->getAmountDue();

        if (!$due) {
            return null;
        }

        foreach ($invoice->getInstallmentCollection() as $installment) {
            // @todo Test.
            if ($installment->getDate()->isGreaterThan($base)) {
                $due = $due->subtract($installment->getAmount());
            }
        }

        if ($due->getAmount() <= 0) {
            return null;
        }

        return $due;
    }
}
