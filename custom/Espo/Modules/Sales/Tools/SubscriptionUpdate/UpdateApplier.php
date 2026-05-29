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

namespace Espo\Modules\Sales\Tools\SubscriptionUpdate;

use Espo\Core\Field\LinkParent;
use Espo\Modules\Sales\Entities\CreditNote;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Entities\SubscriptionPeriod as Period;
use Espo\Modules\Sales\Entities\SubscriptionUpdate as Update;
use Espo\Modules\Sales\Tools\CreditNote\CreditNoteRepository\CreditNoteRepository;
use Espo\Modules\Sales\Tools\Invoice\InvoiceStatusProvider;
use Espo\Modules\Sales\Tools\Payment\Allocation;
use Espo\Modules\Sales\Tools\Quote\BeforeSaveProcessor;
use Espo\Modules\Sales\Tools\Subscription\CreateInvoice\Data as CreateInvoiceData;
use Espo\Modules\Sales\Tools\Subscription\CreateInvoiceForPeriod;
use Espo\Modules\Sales\Tools\Subscription\UpdateAmounts\Data;
use Espo\Modules\Sales\Tools\Subscription\UpdateService;
use Espo\Modules\Sales\Tools\Subscription\Util;
use Espo\ORM\EntityManager;
use RuntimeException;

class UpdateApplier
{
    public function __construct(
        private UpdateService $updateService,
        private EntityManager $entityManager,
        private CreditNoteRepository $creditNoteRepository,
        private Util $util,
        private BeforeSaveProcessor $beforeSaveProcessor,
        private InvoiceStatusProvider $invoiceStatusProvider,
        private CreateInvoiceForPeriod $createInvoiceForPeriod,
    ) {}

    public function apply(Update $update): void
    {
        $this->check($update);

        $toCreateOrders = $update->getBillingStatus() === Update::BILLING_STATUS_PENDING;

        if ($toCreateOrders) {
            $this->createOrders($update);
        }

        $subscription = $update->getSubscription();

        $subscription
            ->setItems($update->getItems())
            ->setAmountCurrency($update->getAmountCurrency());

        $this->updateUpdate($update, $toCreateOrders);

        $this->entityManager->saveEntity($subscription);
    }

    private function check(Update $update): void
    {
        if (!$update->isNew() && $update->getFetchedStatus() !== Update::STATUS_DRAFT) {
            throw new RuntimeException("Can't apply a non-draft update.");
        }
    }

    private function createOrders(Update $update): void
    {
        if ($update->getBillingStatus() !== Update::BILLING_STATUS_PENDING) {
            return;
        }

        $data = Data::fromEntity($update);

        $periods = $this->updateService->preparePeriods($update->getSubscription(), $data);

        foreach ($periods as $period) {
            $this->createOrdersForPeriod($update, $period, $data);
        }
    }

    private function createOrdersForPeriod(Update $update, Period $period, Data $data): void
    {
        [$invoiceToCredit, $invoice] = $this->updateService->prepareInvoices($period, $data);

        $this->amendInvoice($invoice, $update);

        if ($invoice->getItems()) {
            $this->entityManager->saveEntity($invoice);
        }

        $creditNote = $this->prepareCreditNote($invoice, $invoiceToCredit, $update);

        if ($creditNote->getItems()) {
            $this->entityManager->saveEntity($creditNote);
        }

        $this->afterProcessInvoice($update, $invoice, $period);
    }

    private function prepareCreditNote(
        Invoice $invoice,
        Invoice $invoiceToCredit,
        Update $update,
    ): CreditNote {

        $subscription = $update->getSubscription();

        $creditNote = $this->creditNoteRepository->getNew();

        $creditNote
            ->setStatus(CreditNote::STATUS_ISSUED)
            ->setAccount($subscription->getAccountEntity())
            ->setBillingContact($invoice->getBillingContact())
            ->setTax($invoice->getTax())
            ->setIsTaxInclusive($invoice->isTaxInclusive())
            ->setTeams($invoice->getTeams())
            ->setDateIssued($this->util->getToday())
            ->setItems($invoiceToCredit->getItems())
            ->setLocalCurrency($invoice->getLocalCurrency())
            ->setCurrencyRate($invoice->getCurrencyRate())
            ->setRoundingProfile($invoice->getRoundingProfile())
            ->setBuyerReference($invoice->getBuyerReference())
            ->setPurchaseOrderReference($invoice->getPurchaseOrderReference());

        if (!$update->issueBilling()) {
            $creditNote->setStatus(CreditNote::STATUS_DRAFT);
        }

        if ($invoice->getItems()) {
            $creditNote->setInvoice($invoice);
        }

        $this->beforeSaveProcessor->process($creditNote);

        $this->prepareCreditNoteAllocation($invoice, $creditNote);

        $creditNote->setLinkMultipleIdList(CreditNote::LINK_SUBSCRIPTION_UPDATES, [$update->getId()]);

        return $creditNote;
    }

    private function updateUpdate(Update $update, bool $toCreateOrders): void
    {
        $issueBilling = $update->issueBilling();

        $update = $this->getCopy($update);

        $update->setStatus(Update::STATUS_APPLIED);

        $this->controlUpdateBillingStatus($update, $issueBilling, $toCreateOrders);

        $this->entityManager->saveEntity($update);
    }

    /**
     * Prevents loop.
     */
    private function getCopy(Update $update): Update
    {
        return $this->entityManager->getRDBRepositoryByClass(Update::class)->getById($update->getId()) ??
            throw new RuntimeException();
    }

    private function amendInvoice(Invoice $invoice, Update $update): void
    {
        $status = $this->invoiceStatusProvider->getFirstIssued();

        if (!$update->issueBilling()) {
            $status = $this->invoiceStatusProvider->getDraft();
        }

        $invoice->setStatus($status);
        $invoice->setLinkMultipleIdList(Invoice::LINK_SUBSCRIPTION_UPDATES, [$update->getId()]);
    }

    private function prepareCreditNoteAllocation(Invoice $invoice, CreditNote $creditNote): void
    {
        $toProcess =
            $invoice->getItems() &&
            $creditNote->getGrandTotalAmount() &&
            $invoice->getGrandTotalAmount() &&
            $creditNote->getGrandTotalAmount()->getCode() === $invoice->getGrandTotalAmount()->getCode();

        if (!$toProcess) {
            return;
        }

        $amount = $creditNote->getGrandTotalAmount();

        if ($invoice->getGrandTotalAmount()->getAmount() < $amount->getAmount()) {
            $amount = $invoice->getGrandTotalAmount();
        }

        if ($creditNote->getStatus() !== CreditNote::STATUS_DRAFT) {
            $creditNote
                ->setAllocations([
                    new Allocation(LinkParent::createFromEntity($invoice), $amount)
                ]);
        }

        $creditNote
            ->setInvoice($invoice);
    }

    private function afterProcessInvoice(Update $update, Invoice $invoice, Period $period): void
    {
        if (!$this->toAfterProcessInvoice($update, $invoice)) {
            return;
        }

        $data = new CreateInvoiceData(
            createPaymentRequest: $update->createPaymentRequest(),
            sendPaymentRequest: $update->sendPaymentRequest(),
            sendInvoice: $update->sendInvoice(),
        );

        // The real subscription is needed for an email.
        $period->setSubscription($update->getSubscription());

        $this->createInvoiceForPeriod->processPaymentRequestAndEmails($period, $invoice, $data);
    }

    private function toAfterProcessInvoice(Update $update, Invoice $invoice): bool
    {
        return
            $update->issueBilling() &&
            $invoice->getItems() &&
            $invoice->getGrandTotalAmount()?->getAmount();
    }

    private function controlUpdateBillingStatus(Update $update, bool $issueBilling, bool $toCreateOrders): void
    {
        if (!$issueBilling || !$toCreateOrders) {
            return;
        }

        $one = $this->entityManager
            ->getRelation($update, Update::LINK_INVOICES)
            ->findOne();

        $status = $one ?
            Update::BILLING_STATUS_INVOICED :
            Update::BILLING_STATUS_SETTLED;

        $update->setBillingStatus($status);
    }
}
