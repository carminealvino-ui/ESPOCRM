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

namespace Espo\Modules\Sales\Tools\Subscription;

use Espo\Core\Field\Currency;
use Espo\Core\Field\Date;
use Espo\Core\Field\Link;
use Espo\Core\Field\LinkMultiple;
use Espo\Core\Field\LinkMultipleItem;
use Espo\Core\Job\Job\Data as JobData;
use Espo\Core\Job\JobSchedulerFactory;
use Espo\Core\Job\QueueName;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Entities\InvoiceItem;
use Espo\Modules\Sales\Entities\PaymentRequest;
use Espo\Modules\Sales\Entities\Product;
use Espo\Modules\Sales\Entities\Subscription;
use Espo\Modules\Sales\Entities\SubscriptionPeriod as Period;
use Espo\Modules\Sales\Entities\Tax;
use Espo\Modules\Sales\Tools\Invoice\InvoiceOrderItem;
use Espo\Modules\Sales\Tools\Invoice\InvoiceRepository;
use Espo\Modules\Sales\Tools\Invoice\InvoiceStatusProvider;
use Espo\Modules\Sales\Tools\Payment\PaymentMethodProvider;
use Espo\Modules\Sales\Tools\PaymentRequest\PaymentRequestRepository;
use Espo\Modules\Sales\Tools\Product\ProductRepository;
use Espo\Modules\Sales\Tools\Quote\BeforeSaveProcessor;
use Espo\Modules\Sales\Tools\Quote\RoundingUtil;
use Espo\Modules\Sales\Tools\Sales\ConfigDataProvider;
use Espo\Modules\Sales\Tools\Sales\OrderItem;
use Espo\Modules\Sales\Tools\Subscription\Control\BillingCycleHelper;
use Espo\Modules\Sales\Tools\Subscription\CreateInvoice\Data;
use Espo\Modules\Sales\Tools\Subscription\Exceptions\NotProperStatus;
use Espo\Modules\Sales\Tools\Subscription\Jobs\SendInvoice;
use Espo\Modules\Sales\Tools\Subscription\Jobs\SendPaymentRequest;
use Espo\Modules\Sales\Tools\Tax\ProductRateService;
use Espo\Modules\Sales\Tools\TaxRule\RuleService;
use Espo\ORM\EntityManager;
use Espo\ORM\Name\Attribute;
use RuntimeException;

class CreateInvoiceForPeriod
{
    public function __construct(
        private EntityManager $entityManager,
        private ProductRateService $productTaxRateService,
        private RuleService $taxRuleService,
        private BillingCycleHelper $billingCycleHelper,
        private InvoiceRepository $invoiceRepository,
        private ProductRepository $productRepository,
        private PaymentMethodProvider $paymentMethodProvider,
        private InvoiceStatusProvider $invoiceStatusProvider,
        private PaymentRequestRepository $paymentRequestRepository,
        private Util $util,
        private JobSchedulerFactory $jobSchedulerFactory,
        private SubscriptionRepository $subscriptionRepository,
        private RoundingUtil $roundingUtil,
        private BeforeSaveProcessor $beforeSaveProcessor,
        private ConfigDataProvider $configDataProvider,
    ) {}

    /**
     * @throws NotProperStatus
     * @noinspection PhpDocRedundantThrowsInspection
     */
    public function processInTransaction(Period $period, ?Data $data = null): void
    {
        $this->entityManager->getTransactionManager()
            ->run(function () use ($period, $data) {
                $this->process($period, $data);
            });
    }

    /**
     * @throws NotProperStatus
     */
    public function process(Period $period, ?Data $data = null): void
    {
        $this->prepare($period);

        $plan = $period->getSubscription()->getBillingPlan();

        $data ??= new Data(
            createPaymentRequest: $plan->createPaymentRequests(),
            sendPaymentRequest: $plan->sendPaymentRequests(),
            sendInvoice: $plan->sendInvoices(),
        );

        $invoice = $this->createInvoice($period);
        $this->updatePeriod($period);

        $this->processPaymentRequestAndEmails($period, $invoice, $data);
    }

    private function calculateDiffDays(Date $startDate, Date $normalEndDate): int
    {
        $normalDays = $startDate->diff($normalEndDate)->days;

        if (!is_int($normalDays)) {
            throw new RuntimeException("Non-int number of days.");
        }

        return $normalDays;
    }

    private function getItemPrice(SubscriptionOrderItem $baseItem, Subscription $subscription): Currency
    {
        $priceAmount = (float) ($baseItem->get(InvoiceItem::FIELD_UNIT_PRICE) ?? 0.0);
        $currency = $subscription->getAmountCurrency();

        return Currency::create($priceAmount, $currency);
    }

    private function prepareItem(
        SubscriptionOrderItem $baseItem,
        Subscription $subscription,
        ?Tax $tax,
        Period $period,
        float|int $priceFactor,
    ): InvoiceOrderItem {

        $price = $this->getItemPrice($baseItem, $subscription)
            ->multiply($priceFactor);

        $price = $this->roundingUtil->round($price);

        $product = $this->getItemProduct($baseItem);

        $item = InvoiceOrderItem::fromOrderItem($baseItem)
            ->with(Attribute::ID, null)
            ->with(InvoiceItem::FIELD_PRODUCT_IS_SUBSCRIBABLE, $product->isSubscribable());

        if ($tax) {
            $productRate = $this->productTaxRateService->getProductTax($tax, $product);

            if ($productRate) {
                $item = $item
                    ->withTaxRate($productRate->rate)
                    ->withTaxCode($productRate->taxCode);
            }
        }

        return $item
            ->withName($product->getName())
            ->withPeriodStartDate($period->getStartDate())
            ->withPeriodEndDate($period->getEndDate()->addDays(-1))
            ->withListPrice($price)
            ->withUnitPrice($price)
            ->withAmount($price->multiply($item->getQuantity() ?? 0.0));
    }

    private function getItemProduct(OrderItem $baseItem): Product
    {
        if (!$baseItem->getProductId()) {
            throw new RuntimeException("No product in item.");
        }

        $product = $this->productRepository->getById($baseItem->getProductId());

        if (!$product) {
            throw new RuntimeException("Product not found.");
        }

        return $product;
    }

    private function getTax(Subscription $subscription): ?Tax
    {
        $tax = $subscription->getTax();

        if ($tax) {
            return $tax;
        }

        if ($subscription->getAccountEntity()) {
            return $this->taxRuleService->get($subscription->getAccountEntity());
        }

        return null;
    }

    /**
     * @return InvoiceOrderItem[]
     */
    private function prepareItems(
        float|int $priceFactor,
        ?Tax $tax,
        Period $period,
    ): array {

        $subscription = $period->getSubscription();

        $baseItems = $subscription->getItems();

        return array_map(function ($baseItem) use ($priceFactor, $subscription, $tax, $period) {
            return $this->prepareItem(
                baseItem: $baseItem,
                subscription: $subscription,
                tax: $tax,
                period: $period,
                priceFactor: $priceFactor,
            );
        }, $baseItems);
    }

    private function calculatePriceFactor(Period $period): int|float
    {
        $subscription = $period->getSubscription();
        $plan = $subscription->getBillingPlan();

        $anchorDay = $this->billingCycleHelper->findAnchorDay($period->getStartDate(), $subscription);
        $normalEndDate = $this->billingCycleHelper->calculateDateEnd($plan, $period->getStartDate(), $anchorDay);
        $normalDays = $this->calculateDiffDays($period->getStartDate(), $normalEndDate);

        $priceFactor = $plan->getBillingCycleLength();

        if (!$period->getEndDate()->isEqualTo($normalEndDate)) {
            $days = $this->calculateDiffDays($period->getStartDate(), $period->getEndDate());

            $priceFactor = $priceFactor * $days / $normalDays;
        }

        return $priceFactor;
    }

    private function getPaymentMethods(Subscription $subscription): LinkMultiple
    {
        $paymentMethod = $subscription->getPaymentMethod();

        if ($paymentMethod) {
            $item = LinkMultipleItem::create($paymentMethod->getId(), $paymentMethod->getName());

            return LinkMultiple::create([$item]);
        }

        if (!$subscription->getAccountEntity()) {
            return LinkMultiple::create();
        }

        return $this->paymentMethodProvider->getLinkMultipleForAccount($subscription->getAccountEntity());
    }

    private function createPaymentRequest(Invoice $invoice): ?PaymentRequest
    {
        $paymentMethod = $this->getPaymentMethod($invoice);

        if (!$paymentMethod) {
            // @todo Log notice.
            return null;
        }

        $due = $invoice->getAmountDue();

        if (!$due || $due->getAmount() <= 0) {
            return null;
        }

        // @todo Configurable?
        $expirationDate = $invoice->getDateDue()?->addDays(1);

        $request = $this->paymentRequestRepository->getNew();

        $request
            ->setMethod($paymentMethod)
            ->setStatus(PaymentRequest::STATUS_PENDING)
            ->setAmount($due)
            ->setAccount($invoice->getAccountEntity())
            ->setExpirationDate($expirationDate)
            ->setInvoices(
                LinkMultiple::create()
                    ->withAdded(LinkMultipleItem::create($invoice->getId(), $invoice->getName()))
            );

        $this->entityManager->saveEntity($request);

        return $request;
    }

    private function getPaymentMethod(Invoice $invoice): ?Link
    {
        $paymentMethod = $invoice->getPaymentMethods()->getList()[0] ?? null;

        if (!$paymentMethod) {
            return null;
        }

        return Link::create($paymentMethod->getId(), $paymentMethod->getName());
    }

    public function prepareInvoice(Period $period): Invoice
    {
        $tax = $this->getTax($period->getSubscription());

        $items = $this->prepareItems(
            priceFactor: $this->calculatePriceFactor($period),
            tax: $tax,
            period: $period,
        );

        $subscription = $period->getSubscription();
        $plan = $subscription->getBillingPlan();

        $today = $this->util->getToday();

        $dueDays = $subscription->getInvoiceDuePeriodDays() ?? $plan->getInvoiceDuePeriodDays();
        $dateDue = $today->addDays($dueDays);

        $invoice = $this->invoiceRepository->getNew();

        $invoice
            ->setItems($items)
            ->setAccount($subscription->getAccountEntity())
            ->setBillingContact($subscription->getBillingContact())
            ->setTax($tax)
            ->setPriceBook($subscription->getPriceBook())
            ->setIsTaxInclusive($subscription->getPriceBook()?->isTaxInclusive() ?? false)
            ->setPaymentMethods($this->getPaymentMethods($subscription))
            ->setDateInvoiced($today)
            ->setBillingAddress($subscription->getAccountEntity()?->getBillingAddress())
            ->setDateDue($dateDue)
            ->setAmountCurrency($subscription->getAmountCurrency())
            ->setBuyerReference($subscription->getBuyerReference())
            ->setPurchaseOrderReference($subscription->getPurchaseOrderReference())
            ->setTeams($subscription->getTeams());

        if ($period->hasId()) {
            $invoice->setLinkMultipleIdList(Invoice::LINK_SUBSCRIPTION_PERIODS, [$period->getId()]);
        }

        if ($this->configDataProvider->getDefaultRoundingProfile()) {
            $invoice->setRoundingProfile($this->configDataProvider->getDefaultRoundingProfile());
        }

        $this->beforeSaveProcessor->process($invoice);

        return $invoice;
    }

    private function createInvoice(Period $period): Invoice
    {
        $invoice = $this->prepareInvoice($period);

        $invoice->setStatus($this->invoiceStatusProvider->getFirstIssued());

        $this->entityManager->saveEntity($invoice);

        return $invoice;
    }

    private function updatePeriod(Period $period): void
    {
        $period->setBillingStatus(Period::BILLING_STATUS_INVOICED);

        $this->entityManager->saveEntity($period);
    }

    private function processEmails(Period $period, Invoice $invoice, ?PaymentRequest $request, Data $data): void
    {
        if ($request && $data->sendPaymentRequest) {
             $this->createPaymentRequestEmailJob($period, $request);
        }

        if ($data->sendInvoice) {
            $this->jobSchedulerFactory
                ->create()
                ->setClassName(SendInvoice::class)
                ->setQueue(QueueName::E0)
                ->setData(
                    JobData::create()
                        ->withTargetId($invoice->getId())
                        ->withTargetType($invoice->getEntityType())
                )
                ->schedule();
        }
    }

    /**
     * @throws NotProperStatus
     */
    private function prepare(Period $period): void
    {
        $this->subscriptionRepository->refreshAndLock($period->getSubscription());

        $this->entityManager->refreshEntity($period);

        if ($period->getBillingStatus() !== Period::BILLING_STATUS_PENDING) {
            throw new NotProperStatus("Period {$period->getId()} is not pending.");
        }
    }

    public function processPaymentRequestAndEmails(Period $period, Invoice $invoice, Data $data): void
    {
        $paymentRequest = null;

        if ($data->createPaymentRequest) {
            $paymentRequest = $this->createPaymentRequest($invoice);
        }

        $this->processEmails($period, $invoice, $paymentRequest, $data);
    }

    private function createPaymentRequestEmailJob(Period $period, PaymentRequest $request): void
    {
        $data = [
            SendPaymentRequest::PARAM_SUBSCRIPTION_ID => $period->getSubscription()->getId(),
        ];

        if ($period->hasId()) {
            $data[SendPaymentRequest::PARAM_PERIOD_ID] = $period->getId();
        }

        $this->jobSchedulerFactory
            ->create()
            ->setClassName(SendPaymentRequest::class)
            ->setQueue(QueueName::E0)
            ->setData(
                JobData
                    ::create($data)
                    ->withTargetId($request->getId())
                    ->withTargetType($request->getEntityType())
            )
            ->schedule();
    }
}
