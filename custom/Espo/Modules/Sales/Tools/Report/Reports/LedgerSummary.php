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
use Espo\Core\ORM\Type\FieldType;
use Espo\Core\Select\SearchParams;
use Espo\Core\Select\SelectBuilderFactory;
use Espo\Core\Select\Where\Item as WhereItem;
use Espo\Core\Utils\Language;
use Espo\Entities\User;
use Espo\Modules\Advanced\Entities\Report;
use Espo\Modules\Advanced\Reports\GridReport;
use Espo\Modules\Advanced\Tools\Report\GridType\Data\Column;
use Espo\Modules\Advanced\Tools\Report\GridType\Data\ColumnType;
use Espo\Modules\Advanced\Tools\Report\GridType\Data\Group;
use Espo\Modules\Advanced\Tools\Report\GridType\Data\Rows;
use Espo\Modules\Advanced\Tools\Report\GridType\Result;
use Espo\Modules\Advanced\Tools\Report\GridType\ResultData;
use Espo\Modules\Advanced\Tools\Report\GridType\ResultPreparator;
use Espo\Modules\Advanced\Tools\Report\ListType\Result as ListResult;
use Espo\Modules\Advanced\Tools\Report\ListType\SubReportParams;
use Espo\Modules\Sales\Entities\CreditNote;
use Espo\Modules\Sales\Entities\CreditNoteItem;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Entities\InvoiceItem;
use Espo\Modules\Sales\Entities\PaymentAllocation;
use Espo\Modules\Sales\Entities\PaymentEntry;
use Espo\Modules\Sales\Entities\PaymentMethod;
use Espo\Modules\Sales\Entities\QuoteItem;
use Espo\Modules\Sales\Entities\SupplierBill;
use Espo\Modules\Sales\Entities\SupplierBillItem;
use Espo\Modules\Sales\Entities\SupplierCredit;
use Espo\Modules\Sales\Entities\SupplierCreditItem;
use Espo\Modules\Sales\Entities\TaxCode;
use Espo\Modules\Sales\Entities\TaxTotalItem;
use Espo\Modules\Sales\Tools\Payment\PartyType;
use Espo\Modules\Sales\Tools\Payment\Type;
use Espo\Modules\Sales\Tools\Report\Helper\Helper;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\ORM\EntityManager;
use Espo\ORM\Name\Attribute;
use Espo\ORM\Query\Part\Condition as Cond;
use Espo\ORM\Query\Part\Expression as Expr;
use Espo\ORM\Query\Part\Join;
use Espo\ORM\Query\Part\Selection;
use Espo\ORM\Query\Select;
use Espo\ORM\Query\SelectBuilder;
use Espo\ORM\Query\Union;
use Espo\ORM\Query\UnionBuilder;
use PDO;
use RuntimeException;

/**
 * @noinspection PhpUnused
 */
class LedgerSummary implements GridReport
{
    private const ALIAS_DEBIT = 'debit';
    private const ALIAS_CREDIT = 'credit';
    private const ALIAS_GROUP = 'entryLine';
    private const ALIAS_GROUP_LABEL = 'entryLineLabel';
    private const ALIAS_ACCOUNT_NUMBER = 'accountNumber';

    private const GROUP_FUNDS_INBOUND = 'Funds_Inbound';
    private const GROUP_FUNDS_OUTBOUND = 'Funds_Outbound';
    private const GROUP_AR_INVOICES = 'AR_Invoices';
    private const GROUP_AR_CREDITS = 'AR_Credits';
    private const GROUP_AR_WRITE_OFFS = 'AR_WriteOffs';
    private const GROUP_AR_PAYMENTS_INBOUND = 'AR_Payments_Inbound';
    private const GROUP_AR_PAYMENTS_OUTBOUND = 'AR_Payments_Outbound';
    private const GROUP_AR_FX_ADJUSTMENTS = 'AR_FX_Adjustments';
    private const GROUP_TAX_PURCHASE_BILLS = 'Tax_Purchase_Bills';
    private const GROUP_TAX_PURCHASE_CREDITS = 'Tax_Purchase_Credits';
    private const GROUP_REVENUE_INVOICES = 'Revenue_Invoices';
    private const GROUP_REVENUE_CREDITS = 'Revenue_Credits';
    private const GROUP_REVENUE_SHIPPING_INVOICES = 'Revenue_Shipping_Invoices';
    private const GROUP_REVENUE_SHIPPING_CREDITS = 'Revenue_Shipping_Credits';
    private const GROUP_REVENUE_ROUNDING = 'Revenue_Rounding';
    private const GROUP_REVENUE_FX_GAIN_LOSS = 'Revenue_Realized_FX_Gain_Loss';
    private const GROUP_AP_BILLS = 'AP_Bills';
    private const GROUP_AP_CREDITS = 'AP_Credits';
    private const GROUP_AP_PAYMENTS_OUTBOUND = 'AP_Payments_Outbound';
    private const GROUP_AP_PAYMENTS_INBOUND = 'AP_Payments_Inbound';
    private const GROUP_AP_FX_ADJUSTMENTS = 'AP_FX_Adjustments';
    private const GROUP_TAX_SALES_INVOICES = 'Tax_Sales_Invoices';
    private const GROUP_TAX_SALES_CREDITS = 'Tax_Sales_Credits';
    private const GROUP_EXPENSES_BILLS = 'Expenses_Bills';
    private const GROUP_EXPENSES_CREDITS = 'Expenses_Credits';
    private const GROUP_EXPENSES_SHIPPING_BILLS = 'Expenses_Shipping_Bills';
    private const GROUP_EXPENSES_SHIPPING_CREDITS = 'Expenses_Shipping_Credits';
    private const GROUP_EXPENSES_WRITE_OFFS = 'Expenses_WriteOffs';

    private string $currency;

    public function __construct(
        private ConfigDataProvider $configDataProvider,
        private Report $report,
        private EntityManager $entityManager,
        private ResultPreparator $resultPreparator,
        private Language $defaultLanguage,
        private SelectBuilderFactory $selectBuilderFactory,
        private Helper $helper,
    ) {
        $this->currency = $this->configDataProvider->getBaseCurrency();
    }

    public function run(?WhereItem $where, ?User $user): Result
    {
        $this->checkWhere($where);

        $query = $this->prepareQuery($where);

        $rows = $this->entityManager
            ->getQueryExecutor()
            ->execute($query)
            ->fetchAll(PDO::FETCH_ASSOC);

        $this->fixRows($rows);
        $this->filterRows($rows);

        $this->addLabels($rows);

        return $this->prepareResult(
            rows: $rows,
            where: $where,
        );
    }

    public function runSubReport(SearchParams $searchParams, SubReportParams $subReportParams, ?User $user): ListResult
    {
        throw new BadRequest("Not supported.");
    }

    /**
     * @throws BadRequest
     */
    private function prepareQuery(?WhereItem $where): Union
    {
        $unionBuilder = UnionBuilder::create()->all();

        $this->prepareFunds($where, $unionBuilder);
        $this->prepareArInvoices($where, $unionBuilder);
        $this->prepareArCredits($where, $unionBuilder);
        $this->prepareArWriteOffs($where, $unionBuilder);
        $this->prepareArPaymentsInbound($where, $unionBuilder);
        $this->prepareArPaymentsOutbound($where, $unionBuilder);
        $this->prepareArFxAdjustments($where, $unionBuilder);
        $this->prepareTaxPurchase($where, $unionBuilder);
        $this->prepareRevenueInvoices($where, $unionBuilder);
        $this->prepareRevenueCredits($where, $unionBuilder);
        $this->prepareRevenueShippingInvoices($where, $unionBuilder);
        $this->prepareRevenueShippingCredits($where, $unionBuilder);
        $this->prepareRevenueRounding($where, $unionBuilder);
        $this->prepareRevenueFxGainLoss($where, $unionBuilder);
        $this->prepareApBills($where, $unionBuilder);
        $this->prepareApCredits($where, $unionBuilder);
        $this->prepareApPaymentsOutbound($where, $unionBuilder);
        $this->prepareApPaymentsInbound($where, $unionBuilder);
        $this->prepareApFxAdjustments($where, $unionBuilder);
        $this->prepareTaxSales($where, $unionBuilder);
        $this->prepareExpensesBills($where, $unionBuilder);
        $this->prepareExpensesCredits($where, $unionBuilder);
        $this->prepareExpensesShippingBills($where, $unionBuilder);
        $this->prepareExpensesShippingCredits($where, $unionBuilder);
        $this->prepareExpensesWriteOffs($where, $unionBuilder);

        return $unionBuilder->build();
    }

    /**
     * @throws BadRequest
     */
    private function checkWhere(?WhereItem $where): void
    {
        if (!$where) {
            return;
        }

        foreach ($where->getItemList() as $item) {
            if ($item->getAttribute() !== OrderEntity::FIELD_POSTING_DATE) {
                throw new BadRequest("Not allowed where item.");
            }
        }
    }

    private function prepareResult(array $rows, ?WhereItem $where): Result
    {
        $currency = $this->configDataProvider->getBaseCurrency();

        $debitLabel = $this->defaultLanguage
            ->translateLabel(self::ALIAS_DEBIT, 'salesReportColumns', Report::ENTITY_TYPE);
        $creditLabel = $this->defaultLanguage
            ->translateLabel(self::ALIAS_CREDIT, 'salesReportColumns', Report::ENTITY_TYPE);
        $groupLabel = $this->defaultLanguage
            ->translateLabel(self::ALIAS_GROUP, 'salesReportColumns', Report::ENTITY_TYPE);
        $accountNumberLabel = $this->defaultLanguage
            ->translateLabel(self::ALIAS_ACCOUNT_NUMBER, 'salesReportColumns', Report::ENTITY_TYPE);

        $data = new ResultData(
            entityType: Invoice::ENTITY_TYPE,
            group: new Group(
                name: self::ALIAS_GROUP,
                label: $groupLabel,
                valueLabelKey: self::ALIAS_GROUP_LABEL,
            ),
            columns: [
                new Column(
                    name: self::ALIAS_ACCOUNT_NUMBER,
                    label: $accountNumberLabel,
                    fieldType: FieldType::VARCHAR,
                    type: ColumnType::NonSummary,
                    isNumeric: false,
                ),
                new Column(
                    name: self::ALIAS_DEBIT,
                    label: $debitLabel,
                    fieldType: FieldType::CURRENCY,
                ),
                new Column(
                    name: self::ALIAS_CREDIT,
                    label: $creditLabel,
                    fieldType: FieldType::CURRENCY,
                ),
            ],
            currency: $currency,
            noSubReport: true,
        );

        return $this->resultPreparator->prepare($data, Rows::fromAssocList($rows), $where);
    }

    /**
     * @throws BadRequest
     */
    private function prepareFunds(?WhereItem $where, UnionBuilder $unionBuilder): void
    {
        $inboundMethodIds = $this->report->getInternalParams()?->inboundPaymentMethodsIds ?? [];
        $outboundMethodIds = $this->report->getInternalParams()?->outboundPaymentMethodsIds ?? [];

        foreach ($inboundMethodIds as $methodId) {
            $unionBuilder->query(
                $this->prepareFundsQuery(
                    type: Type::Inbound,
                    where: $where,
                    methodId: $methodId,
                )
            );
        }

        $unionBuilder->query(
            $this->prepareFundsQuery(
                type: Type::Inbound,
                where: $where,
                ignoreMethodIds: $inboundMethodIds,
            )
        );

        foreach ($outboundMethodIds as $methodId) {
            $unionBuilder->query(
                $this->prepareFundsQuery(
                    type: Type::Outbound,
                    where: $where,
                    methodId: $methodId,
                )
            );
        }

        $unionBuilder->query(
            $this->prepareFundsQuery(
                type: Type::Outbound,
                where: $where,
                ignoreMethodIds: $outboundMethodIds,
            )
        );
    }

    /**
     * @param ?string[] $ignoreMethodIds
     * @throws BadRequest
     */
    private function prepareFundsQuery(
        Type $type,
        ?WhereItem $where,
        ?string $methodId = null,
        ?array $ignoreMethodIds = null,
    ): Select {

        $builder = $this->selectBuilderFactory
            ->create()
            ->from(PaymentEntry::ENTITY_TYPE)
            ->withWhere($where);

        try {
            $queryBuilder = $builder->buildQueryBuilder();
        } catch (Forbidden $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }

        $queryBuilder->where([
            PaymentEntry::FIELD_TYPE => $type->value,
            PaymentEntry::FIELD_AMOUNT_LOCAL . 'Currency' => $this->currency,
            PaymentEntry::FIELD_STATUS . '!=' => PaymentEntry::STATUS_CANCELED,
            OrderEntity::FIELD_IS_ISSUED => true,
        ]);

        if ($methodId !== null) {
            $queryBuilder->where([
                PaymentEntry::ATTR_METHOD_ID => $methodId,
            ]);
        } else {
            $queryBuilder->where([
                'OR' => [
                    PaymentEntry::ATTR_METHOD_ID . '!=' => $ignoreMethodIds,
                    PaymentEntry::ATTR_METHOD_ID => null,
                ],
            ]);
        }

        $expression = Expr::coalesce(
            Expr::sum(
                Expr::column(PaymentEntry::FIELD_AMOUNT_LOCAL)
            ),
            Expr::value(0)
        );

        $group = $type === Type::Inbound ?
            self::GROUP_FUNDS_INBOUND : self::GROUP_FUNDS_OUTBOUND;

        if ($methodId !== null) {
            $group .= '_' . $methodId;
        }

        $number = $this->report->getInternalParams()->accountNumberFunds ?? null;

        if ($methodId && $type === Type::Inbound) {
            $number =  $this->report->getInternalParams()->inboundPaymentMethodsColumns->$methodId->number ?? null;
        }

        if ($methodId && $type === Type::Outbound) {
            $number =  $this->report->getInternalParams()->outboundPaymentMethodsColumns->$methodId->number ?? null;
        }

        $queryBuilder->select([
            Selection::create(Expr::value($group))
                ->withAlias(self::ALIAS_GROUP),
            Selection::create(Expr::value($number))
                ->withAlias(self::ALIAS_ACCOUNT_NUMBER),
            Selection
                ::create(
                    $type === Type::Inbound ?
                        $expression : Expr::value(0.0)
                )
                ->withAlias(self::ALIAS_DEBIT),
            Selection
                ::create(
                    $type === Type::Outbound ?
                        $expression : Expr::value(0.0)
                )
                ->withAlias(self::ALIAS_CREDIT),
        ]);

        return $queryBuilder->build();
    }

    /**
     * @throws BadRequest
     */
    private function prepareArInvoices(?WhereItem $where, UnionBuilder $unionBuilder): void
    {
        $builder = $this->selectBuilderFactory
            ->create()
            ->from(Invoice::ENTITY_TYPE)
            ->withWhere($where);

        try {
            $queryBuilder = $builder->buildQueryBuilder();
        } catch (Forbidden $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }

        $queryBuilder->where([
            OrderEntity::FIELD_IS_ISSUED => true,
            OrderEntity::FIELD_AMOUNT_LOCAL . 'Currency' => $this->currency,
        ]);

        $number = $this->report->getInternalParams()->accountNumberAr ?? null;

        $expression = Expr::sum(
            Expr::column(OrderEntity::FIELD_GRAND_TOTAL_AMOUNT_LOCAL)
        );

        $queryBuilder->select([
            Selection::create(Expr::value(self::GROUP_AR_INVOICES))->withAlias(self::ALIAS_GROUP),
            Selection::create(Expr::value($number))->withAlias(self::ALIAS_ACCOUNT_NUMBER),
            Selection::create($expression)->withAlias(self::ALIAS_DEBIT),
            Selection::create(Expr::value(0.0))->withAlias(self::ALIAS_CREDIT),
        ]);

        $unionBuilder->query($queryBuilder->build());
    }

    /**
     * @throws BadRequest
     */
    private function prepareArCredits(?WhereItem $where, UnionBuilder $unionBuilder): void
    {
        $builder = $this->selectBuilderFactory
            ->create()
            ->from(CreditNote::ENTITY_TYPE)
            ->withWhere($where);

        try {
            $queryBuilder = $builder->buildQueryBuilder();
        } catch (Forbidden $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }

        $queryBuilder->where([
            OrderEntity::FIELD_IS_ISSUED => true,
            OrderEntity::FIELD_AMOUNT_LOCAL . 'Currency' => $this->currency,
        ]);

        $number = $this->report->getInternalParams()->accountNumberAr ?? null;

        $expression = Expr::sum(
            Expr::column(OrderEntity::FIELD_GRAND_TOTAL_AMOUNT_LOCAL)
        );

        $queryBuilder->select([
            Selection::create(Expr::value(self::GROUP_AR_CREDITS))->withAlias(self::ALIAS_GROUP),
            Selection::create(Expr::value($number))->withAlias(self::ALIAS_ACCOUNT_NUMBER),
            Selection::create(Expr::value(0.0))->withAlias(self::ALIAS_DEBIT),
            Selection::create($expression)->withAlias(self::ALIAS_CREDIT),
        ]);

        $unionBuilder->query($queryBuilder->build());
    }

    /**
     * @throws BadRequest
     */
    private function prepareArWriteOffs(?WhereItem $where, UnionBuilder $unionBuilder): void
    {
        $where = $this->helper->changeWhereItem(
            item: $where,
            from: OrderEntity::FIELD_POSTING_DATE,
            to: PaymentAllocation::FIELD_DATE,
        );

        $builder = $this->selectBuilderFactory
            ->create()
            ->from(PaymentAllocation::ENTITY_TYPE)
            ->withWhere($where);

        try {
            $queryBuilder = $builder->buildQueryBuilder();
        } catch (Forbidden $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }

        $queryBuilder
            ->join(PaymentAllocation::LINK_WRITE_OFF)
            ->where([
                PaymentAllocation::FIELD_AMOUNT_LOCAL . 'Currency' => $this->currency,
            ]);

        $number = $this->report->getInternalParams()->accountNumberAr ?? null;

        $expression = Expr::sum(
            Expr::column(PaymentAllocation::FIELD_AMOUNT_LOCAL)
        );

        $queryBuilder->select([
            Selection::create(Expr::value(self::GROUP_AR_WRITE_OFFS))->withAlias(self::ALIAS_GROUP),
            Selection::create(Expr::value($number))->withAlias(self::ALIAS_ACCOUNT_NUMBER),
            Selection::create(Expr::value(0.0))->withAlias(self::ALIAS_DEBIT),
            Selection::create($expression)->withAlias(self::ALIAS_CREDIT),
        ]);

        $unionBuilder->query($queryBuilder->build());
    }

    /**
     * @throws BadRequest
     */
    private function prepareArPaymentsInbound(?WhereItem $where, UnionBuilder $unionBuilder): void
    {
        $builder = $this->selectBuilderFactory
            ->create()
            ->from(PaymentEntry::ENTITY_TYPE)
            ->withWhere($where);

        try {
            $queryBuilder = $builder->buildQueryBuilder();
        } catch (Forbidden $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }

        $queryBuilder->where([
            PaymentEntry::FIELD_TYPE => Type::Inbound->value,
            PaymentEntry::FIELD_PARTY_TYPE => PartyType::Customer->value,
            PaymentEntry::FIELD_AMOUNT_LOCAL . 'Currency' => $this->currency,
            PaymentEntry::FIELD_STATUS . '!=' => PaymentEntry::STATUS_CANCELED,
            OrderEntity::FIELD_IS_ISSUED => true,
        ]);

        $number = $this->report->getInternalParams()->accountNumberAr ?? null;

        $expression = Expr::sum(
            Expr::column(PaymentEntry::FIELD_AMOUNT_LOCAL)
        );

        $queryBuilder->select([
            Selection::create(Expr::value(self::GROUP_AR_PAYMENTS_INBOUND))->withAlias(self::ALIAS_GROUP),
            Selection::create(Expr::value($number))->withAlias(self::ALIAS_ACCOUNT_NUMBER),
            Selection::create(Expr::value(0.0))->withAlias(self::ALIAS_DEBIT),
            Selection::create($expression)->withAlias(self::ALIAS_CREDIT),
        ]);

        $unionBuilder->query($queryBuilder->build());
    }

    /**
     * @throws BadRequest
     */
    private function prepareArPaymentsOutbound(?WhereItem $where, UnionBuilder $unionBuilder): void
    {
        $builder = $this->selectBuilderFactory
            ->create()
            ->from(PaymentEntry::ENTITY_TYPE)
            ->withWhere($where);

        try {
            $queryBuilder = $builder->buildQueryBuilder();
        } catch (Forbidden $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }

        $queryBuilder->where([
            PaymentEntry::FIELD_TYPE => Type::Outbound->value,
            PaymentEntry::FIELD_PARTY_TYPE => PartyType::Customer->value,
            PaymentEntry::FIELD_AMOUNT_LOCAL . 'Currency' => $this->currency,
            PaymentEntry::FIELD_STATUS . '!=' => PaymentEntry::STATUS_CANCELED,
            OrderEntity::FIELD_IS_ISSUED => true,
        ]);

        $number = $this->report->getInternalParams()->accountNumberAr ?? null;

        $expression = Expr::sum(
            Expr::column(PaymentEntry::FIELD_AMOUNT_LOCAL)
        );

        $queryBuilder->select([
            Selection::create(Expr::value(self::GROUP_AR_PAYMENTS_OUTBOUND))->withAlias(self::ALIAS_GROUP),
            Selection::create(Expr::value($number))->withAlias(self::ALIAS_ACCOUNT_NUMBER),
            Selection::create($expression)->withAlias(self::ALIAS_DEBIT),
            Selection::create(Expr::value(0.0))->withAlias(self::ALIAS_CREDIT),
        ]);

        $unionBuilder->query($queryBuilder->build());
    }

    /**
     * @throws BadRequest
     */
    private function prepareArFxAdjustments(?WhereItem $where, UnionBuilder $unionBuilder): void
    {
        $where = $this->helper->changeWhereItem(
            item: $where,
            from: OrderEntity::FIELD_POSTING_DATE,
            to: PaymentAllocation::FIELD_DATE,
        );

        $builder = $this->selectBuilderFactory
            ->create()
            ->from(PaymentAllocation::ENTITY_TYPE)
            ->withWhere($where);

        try {
            $queryBuilder = $builder->buildQueryBuilder();
        } catch (Forbidden $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }

        $queryBuilder
            ->leftJoin(PaymentAllocation::LINK_PAYMENT_ENTRY)
            ->leftJoin(PaymentAllocation::LINK_CREDIT_NOTE)
            //->leftJoin(PaymentAllocation::LINK_WRITE_OFF)
            ->where([
                'OR' => [
                    PaymentAllocation::LINK_PAYMENT_ENTRY . '.' . PaymentEntry::FIELD_PARTY_TYPE =>
                        PartyType::Customer->value,
                    PaymentAllocation::ATTR_CREDIT_NOTE_ID . '!=' => null,
                    //PaymentAllocation::ATTR_WRITE_OFF_ENTRY_ID . '!=' => null,
                ],
                PaymentAllocation::FIELD_AMOUNT_LOCAL . 'Currency' => $this->currency,
            ]);

        $number = $this->report->getInternalParams()->accountNumberAr ?? null;

        $expressionSum = Expr::sum(
            Expr::column(PaymentAllocation::FIELD_FX_GAIN_LOSS)
        );

        $expressionDebit =
            Expr::if(
                Expr::greater($expressionSum, Expr::value(0)),
                $expressionSum,
                Expr::value(0.0)
            );

        $expressionCredit =
            Expr::if(
                Expr::greater($expressionSum, Expr::value(0)),
                Expr::value(0.0),
                Expr::multiply($expressionSum, Expr::value(-1))
            );

        $queryBuilder->select([
            Selection::create(Expr::value(self::GROUP_AR_FX_ADJUSTMENTS))->withAlias(self::ALIAS_GROUP),
            Selection::create(Expr::value($number))->withAlias(self::ALIAS_ACCOUNT_NUMBER),
            Selection::create($expressionDebit)->withAlias(self::ALIAS_DEBIT),
            Selection::create($expressionCredit)->withAlias(self::ALIAS_CREDIT),
        ]);

        $unionBuilder->query($queryBuilder->build());
    }

    /**
     * @throws BadRequest
     */
    private function prepareTaxPurchase(?WhereItem $where, UnionBuilder $unionBuilder): void
    {
        $taxCodeIds = $this->report->getInternalParams()?->purchaseTaxCodesIds ?? [];

        foreach ($taxCodeIds as $taxCodeId) {
            $unionBuilder->query(
                $this->prepareTaxPurchaseQuery(
                    isCredit: false,
                    where: $where,
                    taxCodeId: $taxCodeId,
                )
            );

            $unionBuilder->query(
                $this->prepareTaxPurchaseQuery(
                    isCredit: true,
                    where: $where,
                    taxCodeId: $taxCodeId,
                )
            );
        }

        $unionBuilder->query(
            $this->prepareTaxPurchaseQuery(
                isCredit: false,
                where: $where,
                ignoreTaxCodeIds: $taxCodeIds,
            )
        );

        $unionBuilder->query(
            $this->prepareTaxPurchaseQuery(
                isCredit: true,
                where: $where,
                ignoreTaxCodeIds: $taxCodeIds,
            )
        );
    }

    /**
     * @param string[] $ignoreTaxCodeIds
     * @throws BadRequest
     */
    private function prepareTaxPurchaseQuery(
        bool $isCredit,
        ?WhereItem $where,
        ?string $taxCodeId = null,
        ?array $ignoreTaxCodeIds = null,
    ): Select {

        $link = TaxTotalItem::FIELD_SOURCE;

        if ($where) {
            $where = $this->helper->changeWhereItem(
                $where,
                OrderEntity::FIELD_POSTING_DATE,
                $link . '.' . OrderEntity::FIELD_POSTING_DATE
            );
        }

        $builder = $this->selectBuilderFactory
            ->create()
            ->from(TaxTotalItem::ENTITY_TYPE)
            ->withWhere($where);

        try {
            $queryBuilder = $builder->buildQueryBuilder();
        } catch (Forbidden $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }

        $entityType = $isCredit ? SupplierCredit::ENTITY_TYPE : SupplierBill::ENTITY_TYPE;

        if ($taxCodeId !== null) {
            $queryBuilder->where([
                TaxTotalItem::ATTR_TAX_CODE_ID => $taxCodeId,
            ]);
        } else {
            $queryBuilder->where([
                TaxTotalItem::ATTR_TAX_CODE_ID . '!=' => $ignoreTaxCodeIds,
            ]);
        }

        $queryBuilder
            ->join(
                Join::createWithTableTarget($entityType, $link)
                    ->withConditions(
                        Cond::and(
                            Cond::equal(
                                Expr::column($link . '.' . Attribute::ID),
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
                $link . '.' . OrderEntity::FIELD_IS_ISSUED => true,
                TaxTotalItem::ATTR_AMOUNT_LOCAL_CURRENCY => $this->configDataProvider->getCurrencyList(),
                TaxTotalItem::ATTR_SOURCE_TYPE => $entityType,
            ]);

        $group = $isCredit ?
            self::GROUP_TAX_PURCHASE_CREDITS : self::GROUP_TAX_PURCHASE_BILLS;

        if ($taxCodeId !== null) {
            $group .= '_' . $taxCodeId;
        }

        $number = $this->report->getInternalParams()->accountNumberTaxPurchase ?? null;

        if ($taxCodeId) {
            $number =  $this->report->getInternalParams()->purchaseTaxCodesColumns->$taxCodeId->number ?? null;
        }

        $expression = Expr::coalesce(
            Expr::sum(
                Expr::column(TaxTotalItem::FIELD_AMOUNT_LOCAL)
            ),
            Expr::value(0)
        );

        $queryBuilder->select([
            Selection::create(Expr::value($group))
                ->withAlias(self::ALIAS_GROUP),
            Selection::create(Expr::value($number))
                ->withAlias(self::ALIAS_ACCOUNT_NUMBER),
            Selection
                ::create(
                    !$isCredit ?
                        $expression : Expr::value(0.0)
                )
                ->withAlias(self::ALIAS_DEBIT),
            Selection
                ::create(
                    $isCredit ?
                        $expression : Expr::value(0.0)
                )
                ->withAlias(self::ALIAS_CREDIT),
        ]);

        return $queryBuilder->build();
    }

    /**
     * @throws BadRequest
     */
    private function prepareRevenueInvoices(?WhereItem $where, UnionBuilder $unionBuilder): void
    {
        $link = InvoiceItem::LINK_INVOICE;

        if ($where) {
            $where = $this->helper->changeWhereItem(
                $where,
                OrderEntity::FIELD_POSTING_DATE,
                $link . '.' . OrderEntity::FIELD_POSTING_DATE
            );
        }

        $builder = $this->selectBuilderFactory
            ->create()
            ->from(InvoiceItem::ENTITY_TYPE)
            ->withWhere($where);

        try {
            $queryBuilder = $builder->buildQueryBuilder();
        } catch (Forbidden $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }

        $queryBuilder
            ->join($link)
            ->where([
                $link . '.' . OrderEntity::FIELD_IS_ISSUED => true,
                $link . '.' . OrderEntity::FIELD_AMOUNT_LOCAL . 'Currency' => $this->currency,
            ]);

        $number = $this->report->getInternalParams()->accountNumberRevenueSales ?? null;

        $expression = Expr::sum(
            Expr::column(QuoteItem::FIELD_AMOUNT_LOCAL)
        );

        $group = self::GROUP_REVENUE_INVOICES;

        $queryBuilder->select([
            Selection::create(Expr::value($group))
                ->withAlias(self::ALIAS_GROUP),
            Selection::create(Expr::value($number))
                ->withAlias(self::ALIAS_ACCOUNT_NUMBER),
            Selection
                ::create(Expr::value(0.0))
                ->withAlias(self::ALIAS_DEBIT),
            Selection
                ::create($expression)
                ->withAlias(self::ALIAS_CREDIT),
        ]);

        $unionBuilder->query($queryBuilder->build());
    }

    /**
     * @throws BadRequest
     */
    private function prepareRevenueCredits(?WhereItem $where, UnionBuilder $unionBuilder): void
    {
        $link = CreditNoteItem::LINK_CREDIT_NOTE;

        if ($where) {
            $where = $this->helper->changeWhereItem(
                $where,
                OrderEntity::FIELD_POSTING_DATE,
                $link . '.' . OrderEntity::FIELD_POSTING_DATE
            );
        }

        $builder = $this->selectBuilderFactory
            ->create()
            ->from(CreditNoteItem::ENTITY_TYPE)
            ->withWhere($where);

        try {
            $queryBuilder = $builder->buildQueryBuilder();
        } catch (Forbidden $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }

        $queryBuilder
            ->join($link)
            ->where([
                $link . '.' . OrderEntity::FIELD_IS_ISSUED => true,
                $link . '.' . OrderEntity::FIELD_AMOUNT_LOCAL . 'Currency' => $this->currency,
            ]);

        $number = $this->report->getInternalParams()->accountNumberRevenueSales ?? null;

        $expression = Expr::sum(
            Expr::column(QuoteItem::FIELD_AMOUNT_LOCAL)
        );

        $group = self::GROUP_REVENUE_CREDITS;

        $queryBuilder->select([
            Selection::create(Expr::value($group))
                ->withAlias(self::ALIAS_GROUP),
            Selection::create(Expr::value($number))
                ->withAlias(self::ALIAS_ACCOUNT_NUMBER),
            Selection
                ::create($expression)
                ->withAlias(self::ALIAS_DEBIT),
            Selection
                ::create(Expr::value(0.0))
                ->withAlias(self::ALIAS_CREDIT),
        ]);

        $unionBuilder->query($queryBuilder->build());
    }

    /**
     * @throws BadRequest
     */
    private function prepareRevenueShippingInvoices(?WhereItem $where, UnionBuilder $unionBuilder): void
    {
        $builder = $this->selectBuilderFactory
            ->create()
            ->from(Invoice::ENTITY_TYPE)
            ->withWhere($where);

        try {
            $queryBuilder = $builder->buildQueryBuilder();
        } catch (Forbidden $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }

        $queryBuilder
            ->where([
                OrderEntity::FIELD_IS_ISSUED => true,
                OrderEntity::FIELD_AMOUNT_LOCAL . 'Currency' => $this->currency,
            ]);

        $number = $this->report->getInternalParams()->accountNumberRevenueSales ?? null;

        $expression = Expr::sum(
            Expr::column(OrderEntity::FIELD_SHIPPING_AMOUNT_LOCAL)
        );

        $group = self::GROUP_REVENUE_SHIPPING_INVOICES;

        $queryBuilder->select([
            Selection::create(Expr::value($group))
                ->withAlias(self::ALIAS_GROUP),
            Selection::create(Expr::value($number))
                ->withAlias(self::ALIAS_ACCOUNT_NUMBER),
            Selection
                ::create(Expr::value(0.0))
                ->withAlias(self::ALIAS_DEBIT),
            Selection
                ::create($expression)
                ->withAlias(self::ALIAS_CREDIT),
        ]);

        $unionBuilder->query($queryBuilder->build());
    }

    /**
     * @throws BadRequest
     */
    private function prepareRevenueShippingCredits(?WhereItem $where, UnionBuilder $unionBuilder): void
    {
        $builder = $this->selectBuilderFactory
            ->create()
            ->from(CreditNote::ENTITY_TYPE)
            ->withWhere($where);

        try {
            $queryBuilder = $builder->buildQueryBuilder();
        } catch (Forbidden $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }

        $queryBuilder
            ->where([
                OrderEntity::FIELD_IS_ISSUED => true,
                OrderEntity::FIELD_AMOUNT_LOCAL . 'Currency' => $this->currency,
            ]);

        $number = $this->report->getInternalParams()->accountNumberRevenueSales ?? null;

        $expression = Expr::sum(
            Expr::column(OrderEntity::FIELD_SHIPPING_AMOUNT_LOCAL)
        );

        $group = self::GROUP_REVENUE_SHIPPING_CREDITS;

        $queryBuilder->select([
            Selection::create(Expr::value($group))
                ->withAlias(self::ALIAS_GROUP),
            Selection::create(Expr::value($number))
                ->withAlias(self::ALIAS_ACCOUNT_NUMBER),
            Selection
                ::create($expression)
                ->withAlias(self::ALIAS_DEBIT),
            Selection
                ::create(Expr::value(0.0))
                ->withAlias(self::ALIAS_CREDIT),
        ]);

        $unionBuilder->query($queryBuilder->build());
    }

    /**
     * @throws BadRequest
     */
    private function prepareRevenueRounding(?WhereItem $where, UnionBuilder $unionBuilder): void
    {
        $builder = SelectBuilder::create()
            ->fromQuery(
                UnionBuilder::create()
                    ->all()
                    ->query($this->prepareRevenueRoundingInvoiceQuery($where))
                    ->query($this->prepareRevenueRoundingCreditQuery($where))
                    ->build(),
                'c'
            );

        $number = $this->report->getInternalParams()->accountNumberRevenueRounding ?? null;

        $debit = Expr::sum(Expr::column('c.' . self::ALIAS_DEBIT));
        $credit = Expr::sum(Expr::column('c.' . self::ALIAS_CREDIT));

        $expressionDebit =
            Expr::if(
                Expr::greater($debit, $credit),
                Expr::subtract($debit, $credit),
                Expr::value(0.0)
            );

        $expressionCredit =
            Expr::if(
                Expr::greater($credit, $debit),
                Expr::subtract($credit, $debit),
                Expr::value(0.0)
            );

        $builder->select([
            Selection::create(Expr::value(self::GROUP_REVENUE_ROUNDING))->withAlias(self::ALIAS_GROUP),
            Selection::create(Expr::value($number))->withAlias(self::ALIAS_ACCOUNT_NUMBER),
            Selection::create($expressionDebit)->withAlias(self::ALIAS_DEBIT),
            Selection::create($expressionCredit)->withAlias(self::ALIAS_CREDIT),
        ]);

        $unionBuilder->query($builder->build());
    }

    /**
     * @throws BadRequest
     */
    private function prepareRevenueRoundingInvoiceQuery(?WhereItem $where): Select
    {
        $builder = $this->selectBuilderFactory
            ->create()
            ->from(Invoice::ENTITY_TYPE)
            ->withWhere($where);

        try {
            $queryBuilder = $builder->buildQueryBuilder();
        } catch (Forbidden $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }

        $queryBuilder
            ->where([
                OrderEntity::FIELD_IS_ISSUED => true,
                OrderEntity::FIELD_AMOUNT_LOCAL . 'Currency' => $this->currency,
            ]);

        $expressionSum = Expr::sum(
            Expr::column(OrderEntity::FIELD_ROUNDING_AMOUNT_LOCAL)
        );

        $expressionDebit =
            Expr::if(
                Expr::greater($expressionSum, Expr::value(0)),
                Expr::value(0.0),
                Expr::multiply($expressionSum, Expr::value(-1))
            );

        $expressionCredit =
            Expr::if(
                Expr::greater($expressionSum, Expr::value(0)),
                $expressionSum,
                Expr::value(0.0)
            );

        $queryBuilder->select([
            Selection::create($expressionDebit)->withAlias(self::ALIAS_DEBIT),
            Selection::create($expressionCredit)->withAlias(self::ALIAS_CREDIT),
        ]);

        return $queryBuilder->build();
    }

    /**
     * @throws BadRequest
     */
    private function prepareRevenueRoundingCreditQuery(?WhereItem $where): Select
    {
        $builder = $this->selectBuilderFactory
            ->create()
            ->from(CreditNote::ENTITY_TYPE)
            ->withWhere($where);

        try {
            $queryBuilder = $builder->buildQueryBuilder();
        } catch (Forbidden $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }

        $queryBuilder
            ->where([
                OrderEntity::FIELD_IS_ISSUED => true,
                OrderEntity::FIELD_AMOUNT_LOCAL . 'Currency' => $this->currency,
            ]);

        $expressionSum = Expr::sum(
            Expr::column(OrderEntity::FIELD_ROUNDING_AMOUNT_LOCAL)
        );

        $expressionDebit =
            Expr::if(
                Expr::greater($expressionSum, Expr::value(0)),
                $expressionSum,
                Expr::value(0.0)
            );

        $expressionCredit =
            Expr::if(
                Expr::greater($expressionSum, Expr::value(0)),
                Expr::value(0.0),
                Expr::multiply($expressionSum, Expr::value(-1))
            );

        $queryBuilder->select([
            Selection::create($expressionDebit)->withAlias(self::ALIAS_DEBIT),
            Selection::create($expressionCredit)->withAlias(self::ALIAS_CREDIT),
        ]);

        return $queryBuilder->build();
    }

    /**
     * @throws BadRequest
     */
    private function prepareRevenueFxGainLoss(?WhereItem $where, UnionBuilder $unionBuilder): void
    {
        $where = $this->helper->changeWhereItem(
            item: $where,
            from: OrderEntity::FIELD_POSTING_DATE,
            to: PaymentAllocation::FIELD_DATE,
        );

        $builder = $this->selectBuilderFactory
            ->create()
            ->from(PaymentAllocation::ENTITY_TYPE)
            ->withWhere($where);

        try {
            $queryBuilder = $builder->buildQueryBuilder();
        } catch (Forbidden $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }

        $queryBuilder
            ->where([
                PaymentAllocation::FIELD_AMOUNT_LOCAL . 'Currency' => $this->currency,
            ]);

        $number = $this->report->getInternalParams()->accountNumberRevenueRealizedFxGainLoss ?? null;

        $expressionSum = Expr::sum(
            Expr::column(PaymentAllocation::FIELD_FX_GAIN_LOSS)
        );

        $expressionDebit =
            Expr::if(
                Expr::greater($expressionSum, Expr::value(0)),
                Expr::value(0.0),
                Expr::multiply($expressionSum, Expr::value(-1)),
            );

        $expressionCredit =
            Expr::if(
                Expr::greater($expressionSum, Expr::value(0)),
                $expressionSum,
                Expr::value(0.0),
            );

        $queryBuilder->select([
            Selection::create(Expr::value(self::GROUP_REVENUE_FX_GAIN_LOSS))->withAlias(self::ALIAS_GROUP),
            Selection::create(Expr::value($number))->withAlias(self::ALIAS_ACCOUNT_NUMBER),
            Selection::create($expressionDebit)->withAlias(self::ALIAS_DEBIT),
            Selection::create($expressionCredit)->withAlias(self::ALIAS_CREDIT),
        ]);

        $unionBuilder->query($queryBuilder->build());
    }

    /**
     * @throws BadRequest
     */
    private function prepareApBills(?WhereItem $where, UnionBuilder $unionBuilder): void
    {
        $builder = $this->selectBuilderFactory
            ->create()
            ->from(SupplierBill::ENTITY_TYPE)
            ->withWhere($where);

        try {
            $queryBuilder = $builder->buildQueryBuilder();
        } catch (Forbidden $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }

        $queryBuilder->where([
            OrderEntity::FIELD_IS_ISSUED => true,
            OrderEntity::FIELD_AMOUNT_LOCAL . 'Currency' => $this->currency,
        ]);

        $number = $this->report->getInternalParams()->accountNumberAp ?? null;

        $expression = Expr::sum(
            Expr::column(OrderEntity::FIELD_GRAND_TOTAL_AMOUNT_LOCAL)
        );

        $queryBuilder->select([
            Selection::create(Expr::value(self::GROUP_AP_BILLS))->withAlias(self::ALIAS_GROUP),
            Selection::create(Expr::value($number))->withAlias(self::ALIAS_ACCOUNT_NUMBER),
            Selection::create(Expr::value(0.0))->withAlias(self::ALIAS_DEBIT),
            Selection::create($expression)->withAlias(self::ALIAS_CREDIT),
        ]);

        $unionBuilder->query($queryBuilder->build());
    }

    /**
     * @throws BadRequest
     */
    private function prepareApCredits(?WhereItem $where, UnionBuilder $unionBuilder): void
    {
        $builder = $this->selectBuilderFactory
            ->create()
            ->from(SupplierCredit::ENTITY_TYPE)
            ->withWhere($where);

        try {
            $queryBuilder = $builder->buildQueryBuilder();
        } catch (Forbidden $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }

        $queryBuilder->where([
            OrderEntity::FIELD_IS_ISSUED => true,
            OrderEntity::FIELD_AMOUNT_LOCAL . 'Currency' => $this->currency,
        ]);

        $number = $this->report->getInternalParams()->accountNumberAp ?? null;

        $expression = Expr::sum(
            Expr::column(OrderEntity::FIELD_GRAND_TOTAL_AMOUNT_LOCAL)
        );

        $queryBuilder->select([
            Selection::create(Expr::value(self::GROUP_AP_CREDITS))->withAlias(self::ALIAS_GROUP),
            Selection::create(Expr::value($number))->withAlias(self::ALIAS_ACCOUNT_NUMBER),
            Selection::create($expression)->withAlias(self::ALIAS_DEBIT),
            Selection::create(Expr::value(0.0))->withAlias(self::ALIAS_CREDIT),
        ]);

        $unionBuilder->query($queryBuilder->build());
    }

    /**
     * @throws BadRequest
     */
    private function prepareApPaymentsOutbound(?WhereItem $where, UnionBuilder $unionBuilder): void
    {
        $builder = $this->selectBuilderFactory
            ->create()
            ->from(PaymentEntry::ENTITY_TYPE)
            ->withWhere($where);

        try {
            $queryBuilder = $builder->buildQueryBuilder();
        } catch (Forbidden $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }

        $queryBuilder->where([
            PaymentEntry::FIELD_TYPE => Type::Outbound->value,
            PaymentEntry::FIELD_PARTY_TYPE => PartyType::Supplier->value,
            PaymentEntry::FIELD_AMOUNT_LOCAL . 'Currency' => $this->currency,
            PaymentEntry::FIELD_STATUS . '!=' => PaymentEntry::STATUS_CANCELED,
            OrderEntity::FIELD_IS_ISSUED => true,
        ]);

        $number = $this->report->getInternalParams()->accountNumberAp ?? null;

        $expression = Expr::sum(
            Expr::column(PaymentEntry::FIELD_AMOUNT_LOCAL)
        );

        $queryBuilder->select([
            Selection::create(Expr::value(self::GROUP_AP_PAYMENTS_OUTBOUND))->withAlias(self::ALIAS_GROUP),
            Selection::create(Expr::value($number))->withAlias(self::ALIAS_ACCOUNT_NUMBER),
            Selection::create($expression)->withAlias(self::ALIAS_DEBIT),
            Selection::create(Expr::value(0.0))->withAlias(self::ALIAS_CREDIT),
        ]);

        $unionBuilder->query($queryBuilder->build());
    }

    /**
     * @throws BadRequest
     */
    private function prepareApPaymentsInbound(?WhereItem $where, UnionBuilder $unionBuilder): void
    {
        $builder = $this->selectBuilderFactory
            ->create()
            ->from(PaymentEntry::ENTITY_TYPE)
            ->withWhere($where);

        try {
            $queryBuilder = $builder->buildQueryBuilder();
        } catch (Forbidden $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }

        $queryBuilder->where([
            PaymentEntry::FIELD_TYPE => Type::Inbound->value,
            PaymentEntry::FIELD_PARTY_TYPE => PartyType::Supplier->value,
            PaymentEntry::FIELD_AMOUNT_LOCAL . 'Currency' => $this->currency,
            PaymentEntry::FIELD_STATUS . '!=' => PaymentEntry::STATUS_CANCELED,
            OrderEntity::FIELD_IS_ISSUED => true,
        ]);

        $number = $this->report->getInternalParams()->accountNumberAp ?? null;

        $expression = Expr::sum(
            Expr::column(PaymentEntry::FIELD_AMOUNT_LOCAL)
        );

        $queryBuilder->select([
            Selection::create(Expr::value(self::GROUP_AP_PAYMENTS_INBOUND))->withAlias(self::ALIAS_GROUP),
            Selection::create(Expr::value($number))->withAlias(self::ALIAS_ACCOUNT_NUMBER),
            Selection::create(Expr::value(0.0))->withAlias(self::ALIAS_DEBIT),
            Selection::create($expression)->withAlias(self::ALIAS_CREDIT),
        ]);

        $unionBuilder->query($queryBuilder->build());
    }

    /**
     * @throws BadRequest
     */
    private function prepareApFxAdjustments(?WhereItem $where, UnionBuilder $unionBuilder): void
    {
        $where = $this->helper->changeWhereItem(
            item: $where,
            from: OrderEntity::FIELD_POSTING_DATE,
            to: PaymentAllocation::FIELD_DATE,
        );

        $builder = $this->selectBuilderFactory
            ->create()
            ->from(PaymentAllocation::ENTITY_TYPE)
            ->withWhere($where);

        try {
            $queryBuilder = $builder->buildQueryBuilder();
        } catch (Forbidden $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }

        $queryBuilder
            ->leftJoin(PaymentAllocation::LINK_PAYMENT_ENTRY)
            ->leftJoin(PaymentAllocation::LINK_SUPPLIER_CREDIT)
            ->where([
                'OR' => [
                    PaymentAllocation::LINK_PAYMENT_ENTRY . '.' . PaymentEntry::FIELD_PARTY_TYPE =>
                        PartyType::Supplier->value,
                    PaymentAllocation::ATTR_SUPPLIER_CREDIT_ID . '!=' => null,
                ],
                PaymentAllocation::FIELD_AMOUNT_LOCAL . 'Currency' => $this->currency,
            ]);

        $number = $this->report->getInternalParams()->accountNumberAp ?? null;

        $expressionSum = Expr::sum(
            Expr::column(PaymentAllocation::FIELD_FX_GAIN_LOSS)
        );

        $expressionDebit =
            Expr::if(
                Expr::greater($expressionSum, Expr::value(0)),
                $expressionSum,
                Expr::value(0.0)
            );

        $expressionCredit =
            Expr::if(
                Expr::greater($expressionSum, Expr::value(0)),
                Expr::value(0.0),
                Expr::multiply($expressionSum, Expr::value(-1))
            );

        $queryBuilder->select([
            Selection::create(Expr::value(self::GROUP_AP_FX_ADJUSTMENTS))->withAlias(self::ALIAS_GROUP),
            Selection::create(Expr::value($number))->withAlias(self::ALIAS_ACCOUNT_NUMBER),
            Selection::create($expressionDebit)->withAlias(self::ALIAS_DEBIT),
            Selection::create($expressionCredit)->withAlias(self::ALIAS_CREDIT),
        ]);

        $unionBuilder->query($queryBuilder->build());
    }

    /**
     * @throws BadRequest
     */
    private function prepareTaxSales(?WhereItem $where, UnionBuilder $unionBuilder): void
    {
        $taxCodeIds = $this->report->getInternalParams()?->salesTaxCodesIds ?? [];

        foreach ($taxCodeIds as $taxCodeId) {
            $unionBuilder->query(
                $this->prepareTaxSalesQuery(
                    isCredit: false,
                    where: $where,
                    taxCodeId: $taxCodeId,
                )
            );

            $unionBuilder->query(
                $this->prepareTaxSalesQuery(
                    isCredit: true,
                    where: $where,
                    taxCodeId: $taxCodeId,
                )
            );
        }

        $unionBuilder->query(
            $this->prepareTaxSalesQuery(
                isCredit: false,
                where: $where,
                ignoreTaxCodeIds: $taxCodeIds,
            )
        );

        $unionBuilder->query(
            $this->prepareTaxSalesQuery(
                isCredit: true,
                where: $where,
                ignoreTaxCodeIds: $taxCodeIds,
            )
        );
    }

    /**
     * @param string[] $ignoreTaxCodeIds
     * @throws BadRequest
     */
    private function prepareTaxSalesQuery(
        bool $isCredit,
        ?WhereItem $where,
        ?string $taxCodeId = null,
        ?array $ignoreTaxCodeIds = null,
    ): Select {

        $link = TaxTotalItem::FIELD_SOURCE;

        if ($where) {
            $where = $this->helper->changeWhereItem(
                $where,
                OrderEntity::FIELD_POSTING_DATE,
                $link . '.' . OrderEntity::FIELD_POSTING_DATE
            );
        }

        $builder = $this->selectBuilderFactory
            ->create()
            ->from(TaxTotalItem::ENTITY_TYPE)
            ->withWhere($where);

        try {
            $queryBuilder = $builder->buildQueryBuilder();
        } catch (Forbidden $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }

        $entityType = $isCredit ? CreditNote::ENTITY_TYPE : Invoice::ENTITY_TYPE;

        if ($taxCodeId !== null) {
            $queryBuilder->where([
                TaxTotalItem::ATTR_TAX_CODE_ID => $taxCodeId,
            ]);
        } else {
            $queryBuilder->where([
                TaxTotalItem::ATTR_TAX_CODE_ID . '!=' => $ignoreTaxCodeIds,
            ]);
        }

        $queryBuilder
            ->join(
                Join::createWithTableTarget($entityType, $link)
                    ->withConditions(
                        Cond::and(
                            Cond::equal(
                                Expr::column($link . '.' . Attribute::ID),
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
                $link . '.' . OrderEntity::FIELD_IS_ISSUED => true,
                TaxTotalItem::ATTR_AMOUNT_LOCAL_CURRENCY => $this->configDataProvider->getCurrencyList(),
                TaxTotalItem::ATTR_SOURCE_TYPE => $entityType,
            ]);

        $group = $isCredit ?
            self::GROUP_TAX_SALES_CREDITS : self::GROUP_TAX_SALES_INVOICES;

        if ($taxCodeId !== null) {
            $group .= '_' . $taxCodeId;
        }

        $number = $this->report->getInternalParams()->accountNumberTaxSales ?? null;

        if ($taxCodeId) {
            $number =  $this->report->getInternalParams()->salesTaxCodesColumns->$taxCodeId->number ?? null;
        }

        $expression = Expr::sum(
            Expr::column(TaxTotalItem::FIELD_AMOUNT_LOCAL)
        );

        $queryBuilder->select([
            Selection::create(Expr::value($group))
                ->withAlias(self::ALIAS_GROUP),
            Selection::create(Expr::value($number))
                ->withAlias(self::ALIAS_ACCOUNT_NUMBER),
            Selection
                ::create(
                    $isCredit ?
                        $expression : Expr::value(0.0)
                )
                ->withAlias(self::ALIAS_DEBIT),
            Selection
                ::create(
                    !$isCredit ?
                        $expression : Expr::value(0.0)
                )
                ->withAlias(self::ALIAS_CREDIT),
        ]);

        return $queryBuilder->build();
    }

    /**
     * @throws BadRequest
     */
    private function prepareExpensesBills(?WhereItem $where, UnionBuilder $unionBuilder): void
    {
        $link = SupplierBillItem::LINK_SUPPLIER_BILL;

        if ($where) {
            $where = $this->helper->changeWhereItem(
                $where,
                OrderEntity::FIELD_POSTING_DATE,
                $link . '.' . OrderEntity::FIELD_POSTING_DATE
            );
        }

        $builder = $this->selectBuilderFactory
            ->create()
            ->from(SupplierBillItem::ENTITY_TYPE)
            ->withWhere($where);

        try {
            $queryBuilder = $builder->buildQueryBuilder();
        } catch (Forbidden $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }

        $queryBuilder
            ->join($link)
            ->where([
                $link . '.' . OrderEntity::FIELD_IS_ISSUED => true,
                $link . '.' . OrderEntity::FIELD_AMOUNT_LOCAL . 'Currency' => $this->currency,
            ]);

        $number = $this->report->getInternalParams()->accountNumberExpensesPurchase ?? null;

        $expression = Expr::sum(
            Expr::column(QuoteItem::FIELD_AMOUNT_LOCAL)
        );

        $group = self::GROUP_EXPENSES_BILLS;

        $queryBuilder->select([
            Selection::create(Expr::value($group))
                ->withAlias(self::ALIAS_GROUP),
            Selection::create(Expr::value($number))
                ->withAlias(self::ALIAS_ACCOUNT_NUMBER),
            Selection
                ::create($expression)
                ->withAlias(self::ALIAS_DEBIT),
            Selection
                ::create(Expr::value(0.0))
                ->withAlias(self::ALIAS_CREDIT),
        ]);

        $unionBuilder->query($queryBuilder->build());
    }

    /**
     * @throws BadRequest
     */
    private function prepareExpensesCredits(?WhereItem $where, UnionBuilder $unionBuilder): void
    {
        $link = SupplierCreditItem::LINK_SUPPLIER_CREDIT;

        if ($where) {
            $where = $this->helper->changeWhereItem(
                $where,
                OrderEntity::FIELD_POSTING_DATE,
                $link . '.' . OrderEntity::FIELD_POSTING_DATE
            );
        }

        $builder = $this->selectBuilderFactory
            ->create()
            ->from(SupplierCreditItem::ENTITY_TYPE)
            ->withWhere($where);

        try {
            $queryBuilder = $builder->buildQueryBuilder();
        } catch (Forbidden $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }

        $queryBuilder
            ->join($link)
            ->where([
                $link . '.' . OrderEntity::FIELD_IS_ISSUED => true,
                $link . '.' . OrderEntity::FIELD_AMOUNT_LOCAL . 'Currency' => $this->currency,
            ]);

        $number = $this->report->getInternalParams()->accountNumberExpensesPurchase ?? null;

        $expression = Expr::sum(
            Expr::column(QuoteItem::FIELD_AMOUNT_LOCAL)
        );

        $group = self::GROUP_EXPENSES_CREDITS;

        $queryBuilder->select([
            Selection::create(Expr::value($group))
                ->withAlias(self::ALIAS_GROUP),
            Selection::create(Expr::value($number))
                ->withAlias(self::ALIAS_ACCOUNT_NUMBER),
            Selection
                ::create(Expr::value(0.0))
                ->withAlias(self::ALIAS_DEBIT),
            Selection
                ::create($expression)
                ->withAlias(self::ALIAS_CREDIT),
        ]);

        $unionBuilder->query($queryBuilder->build());
    }

    /**
     * @throws BadRequest
     */
    private function prepareExpensesShippingBills(?WhereItem $where, UnionBuilder $unionBuilder): void
    {
        $builder = $this->selectBuilderFactory
            ->create()
            ->from(SupplierBill::ENTITY_TYPE)
            ->withWhere($where);

        try {
            $queryBuilder = $builder->buildQueryBuilder();
        } catch (Forbidden $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }

        $queryBuilder
            ->where([
                OrderEntity::FIELD_IS_ISSUED => true,
                OrderEntity::FIELD_AMOUNT_LOCAL . 'Currency' => $this->currency,
            ]);

        $number = $this->report->getInternalParams()->accountNumberExpensesPurchase ?? null;

        $expression = Expr::sum(
            Expr::column(OrderEntity::FIELD_SHIPPING_AMOUNT_LOCAL)
        );

        $group = self::GROUP_EXPENSES_SHIPPING_BILLS;

        $queryBuilder->select([
            Selection::create(Expr::value($group))
                ->withAlias(self::ALIAS_GROUP),
            Selection::create(Expr::value($number))
                ->withAlias(self::ALIAS_ACCOUNT_NUMBER),
            Selection
                ::create($expression)
                ->withAlias(self::ALIAS_DEBIT),
            Selection
                ::create(Expr::value(0.0))
                ->withAlias(self::ALIAS_CREDIT),
        ]);

        $unionBuilder->query($queryBuilder->build());
    }

    /**
     * @throws BadRequest
     */
    private function prepareExpensesShippingCredits(?WhereItem $where, UnionBuilder $unionBuilder): void
    {
        $builder = $this->selectBuilderFactory
            ->create()
            ->from(SupplierCredit::ENTITY_TYPE)
            ->withWhere($where);

        try {
            $queryBuilder = $builder->buildQueryBuilder();
        } catch (Forbidden $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }

        $queryBuilder
            ->where([
                OrderEntity::FIELD_IS_ISSUED => true,
                OrderEntity::FIELD_AMOUNT_LOCAL . 'Currency' => $this->currency,
            ]);

        $number = $this->report->getInternalParams()->accountNumberExpensesPurchase ?? null;

        $expression = Expr::sum(
            Expr::column(OrderEntity::FIELD_SHIPPING_AMOUNT_LOCAL)
        );

        $group = self::GROUP_EXPENSES_SHIPPING_CREDITS;

        $queryBuilder->select([
            Selection::create(Expr::value($group))
                ->withAlias(self::ALIAS_GROUP),
            Selection::create(Expr::value($number))
                ->withAlias(self::ALIAS_ACCOUNT_NUMBER),
            Selection
                ::create(Expr::value(0.0))
                ->withAlias(self::ALIAS_DEBIT),
            Selection
                ::create($expression)
                ->withAlias(self::ALIAS_CREDIT),
        ]);

        $unionBuilder->query($queryBuilder->build());
    }

    /**
     * @throws BadRequest
     */
    private function prepareExpensesWriteOffs(?WhereItem $where, UnionBuilder $unionBuilder): void
    {
        $where = $this->helper->changeWhereItem(
            item: $where,
            from: OrderEntity::FIELD_POSTING_DATE,
            to: PaymentAllocation::FIELD_DATE,
        );

        $builder = $this->selectBuilderFactory
            ->create()
            ->from(PaymentAllocation::ENTITY_TYPE)
            ->withWhere($where);

        try {
            $queryBuilder = $builder->buildQueryBuilder();
        } catch (Forbidden $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }

        $queryBuilder
            ->join(PaymentAllocation::LINK_WRITE_OFF)
            ->where([
                PaymentAllocation::FIELD_AMOUNT_LOCAL . 'Currency' => $this->currency,
            ]);

        $number = $this->report->getInternalParams()->accountNumberExpensesWriteOffs ?? null;

        $expression = Expr::sum(
            Expr::column(PaymentAllocation::FIELD_AMOUNT_LOCAL)
        );

        $queryBuilder->select([
            Selection::create(Expr::value(self::GROUP_EXPENSES_WRITE_OFFS))->withAlias(self::ALIAS_GROUP),
            Selection::create(Expr::value($number))->withAlias(self::ALIAS_ACCOUNT_NUMBER),
            Selection::create($expression)->withAlias(self::ALIAS_DEBIT),
            Selection::create(Expr::value(0.0))->withAlias(self::ALIAS_CREDIT),
        ]);

        $unionBuilder->query($queryBuilder->build());
    }

    /**
     * @param array<string, mixed> $rows
     */
    private function addLabels(array &$rows): void
    {
        foreach ($rows as &$row) {
            $row[self::ALIAS_GROUP_LABEL] = $this->translateLineLabel($row[self::ALIAS_GROUP]);
        }
    }

    private function translateLineLabel(string $key): ?string
    {
        $map = [
            self::GROUP_FUNDS_INBOUND => PaymentMethod::class,
            self::GROUP_FUNDS_OUTBOUND => PaymentMethod::class,
            self::GROUP_TAX_SALES_INVOICES => TaxCode::class,
            self::GROUP_TAX_SALES_CREDITS => TaxCode::class,
            self::GROUP_TAX_PURCHASE_BILLS => TaxCode::class,
            self::GROUP_TAX_PURCHASE_CREDITS => TaxCode::class,
        ];

        $pos = strrpos($key, '_') ?: 0;
        $baseKey = substr($key, 0, $pos);

        if (array_key_exists($baseKey, $map)) {
            $className = $map[$baseKey];

            $id = substr($key, $pos + 1);
            $name = $this->getEntityName($className, $id);

            return $this->defaultLanguage->translate($baseKey, 'salesLedgerLabels', 'Report') .
                ' · ' . $name;
        }

        return $this->defaultLanguage->translate($key, 'salesLedgerLabels', 'Report');
    }

    /**
     * @param class-string<TaxCode|PaymentMethod> $className
     * @param string $id
     * @return string|null
     */
    private function getEntityName(string $className, string $id): ?string
    {
        $name = $id;

        if ($id) {
            $method = $this->entityManager->getRDBRepositoryByClass($className)->getById($id);
            $name = $method->getName() ?? null;
        }

        return $name;
    }

    /**
     * @param array<string, mixed>[] $rows
     */
    private function fixRows(array &$rows): void
    {
        foreach ($rows as &$row) {
            if ($row[self::ALIAS_DEBIT] === null) {
                $row[self::ALIAS_DEBIT] = '0.0';
            }

            if ($row[self::ALIAS_CREDIT] === null) {
                $row[self::ALIAS_CREDIT] = '0.0';
            }
        }
    }

    /**
     * @param array<string, mixed>[] $rows
     */
    private function filterRows(array &$rows): void
    {
        if (!($this->report->getInternalParams()?->includeZero ?? false)) {
            $rows = array_filter($rows, function ($row) {
                return
                    CalculatorUtil::compare($row[self::ALIAS_DEBIT], '0') !== 0 ||
                    CalculatorUtil::compare($row[self::ALIAS_CREDIT], '0') !== 0;
            });

            $rows = array_values($rows);
        }
    }
}
