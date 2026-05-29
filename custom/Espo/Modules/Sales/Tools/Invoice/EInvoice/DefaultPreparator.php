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

namespace Espo\Modules\Sales\Tools\Invoice\EInvoice;

use DateTime;
use Einvoicing\Identifier;
use Einvoicing\Invoice as EInvoice;
use Einvoicing\Payments\Mandate;
use Einvoicing\Payments\Payment;
use Einvoicing\Payments\Transfer;
use Espo\Core\Field\Date;
use Espo\Core\ORM\Entity;
use Espo\Modules\Crm\Entities\Account;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Entities\PaymentChannelSepaCreditTransfer;
use Espo\Modules\Sales\Entities\PaymentChannelSepaDirectDebit;
use Espo\Modules\Sales\Entities\PaymentChannelWireTransfer;
use Espo\Modules\Sales\Entities\PaymentMandateSepa;
use Espo\Modules\Sales\Entities\PaymentMethod;
use Espo\Modules\Sales\Tools\Invoice\EInvoice\Exceptions\UnknownFormat;
use Espo\Modules\Sales\Tools\Invoice\EInvoice\Exceptions\UnsupportedTaxCombination;
use Espo\Modules\Sales\Tools\PaymentMandate\MandateProvider;
use Espo\ORM\EntityManager;
use Exception;
use RuntimeException;

class DefaultPreparator implements Preparator
{
    public function __construct(
        private EntityManager $entityManager,
        private MandateProvider $mandateProvider,
        private Helper $helper,
    ) {}

    /**
     * @throws UnknownFormat
     * @throws UnsupportedTaxCombination
     */
    public function prepare(Invoice $invoice, string $format): EInvoice
    {
        $eInvoice = $this->helper->prepareNew($format);

        if ($invoice->getType() === Invoice::TYPE_DEBIT_NOTE) {
            $eInvoice->setType(EInvoice::TYPE_DEBIT_NOTE);
        }

        if ($invoice->getNumber()) {
            $eInvoice->setNumber($invoice->getNumber());
        }

        if ($invoice->getDateInvoiced()) {
            $eInvoice->setIssueDate($this->convertDate($invoice->getDateInvoiced()));
        }

        if ($invoice->getDateDue()) {
            $eInvoice->setDueDate($this->convertDate($invoice->getDateDue()));
        }

        if ($invoice->getPaymentTermsNote()) {
            $eInvoice->setPaymentTerms($invoice->getPaymentTermsNote());
        }

        if ($invoice->getAmount()) {
            $eInvoice->setCurrency($invoice->getAmount()->getCode());
        }

        if ($invoice->getBuyerReference()) {
            $eInvoice->setBuyerReference($invoice->getBuyerReference());
        }

        if ($invoice->getPurchaseOrderReference()) {
            $eInvoice->setPurchaseOrderReference($invoice->getPurchaseOrderReference());
        }

        if ($invoice->getNote()) {
            $eInvoice->addNote($invoice->getNote());
        }

        if ($invoice->getType() === Invoice::TYPE_DEBIT_NOTE && $invoice->getPrecedingInvoice()?->getNumber()) {
            $this->helper->addInvoiceReference($eInvoice, $invoice->getPrecedingInvoice());
        }

        $eInvoice->setSeller($this->helper->prepareSeller());

        $account = $this->getAccount($invoice);

        if ($account) {
            $eInvoice->setBuyer($this->helper->prepareBuyer($account, $invoice));
        }

        foreach ($this->preparePayments($invoice, $account, $eInvoice) as $payment) {
            $eInvoice->addPayment($payment);
        }

        $delivery = $this->helper->prepareDelivery($invoice);

        $eInvoice->setDelivery($delivery);

        $this->helper->addLines($invoice, $eInvoice);
        $this->helper->addShippingItems($invoice, $eInvoice);
        $this->helper->addRounding($invoice, $eInvoice);

        return $eInvoice;
    }

    private function convertDate(Date $date): DateTime
    {
        try {
            return new DateTime($date->toString());
        } catch (Exception $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }
    }

    private function getAccount(Invoice $invoice): ?Account
    {
        $account = null;

        if ($invoice->getAccount()) {
            $account = $this->entityManager
                ->getRDBRepositoryByClass(Account::class)
                ->getById($invoice->getAccount()->getId());
        }

        return $account;
    }

    /**
     * @param Invoice $invoice
     * @return Payment[]
     */
    private function preparePayments(Invoice $invoice, ?Account $account, EInvoice $eInvoice): array
    {
        $output = [];

        foreach ($invoice->getPaymentMethodCollection() as $method) {
            $payment = $this->preparePayment($method, $account, $eInvoice);

            if (!$payment) {
                continue;
            }

            if ($method->get('isPrimary')) {
                array_unshift($output, $payment);

                continue;
            }

            $output[] = $payment;
        }

        return $output;
    }

    private function preparePayment(
        PaymentMethod $method,
        ?Account $account,
        EInvoice $eInvoice,
    ): ?Payment {
        $payment = new Payment();

        $record = $method->getChannel()?->getRecord();

        $code = $this->getPaymentMeansCode($record);

        if ($code === null) {
            return null;
        }

        $payment->setMeansCode($code);

        $transfer = $this->preparePaymentTransfer($record);

        if ($transfer) {
            $payment->addTransfer($transfer);
        }

        $mandate = $this->prepareMandate($account, $record, $eInvoice);

        if ($mandate) {
            $payment->setMandate($mandate);
        }

        return $payment;
    }

    private function preparePaymentTransfer(?Entity $record): ?Transfer
    {
        $transfer = null;

        if ($record instanceof PaymentChannelWireTransfer) {
            $transfer = $this->prepareWireTransferPaymentTransfer($record);
        }

        if ($record instanceof PaymentChannelSepaCreditTransfer) {
            $transfer = $this->prepareSepaCreditTransferPaymentTransfer($record);
        }

        return $transfer;
    }

    private function prepareWireTransferPaymentTransfer(PaymentChannelWireTransfer $record): Transfer
    {
        $transfer = new Transfer();

        if ($record->getAccountHolder()) {
            $transfer->setAccountName($record->getAccountHolder());
        }

        $accountId = $record->getIban() ?? $record->getAccountNumber();

        if ($accountId) {
            $transfer->setAccountId($accountId);
        }

        if ($record->getBic()) {
            $transfer->setProvider($record->getBic());
        }

        return $transfer;
    }

    private function prepareSepaCreditTransferPaymentTransfer(PaymentChannelSepaCreditTransfer $record): Transfer
    {
        $transfer = new Transfer();

        if ($record->getAccountHolder()) {
            $transfer->setAccountName($record->getAccountHolder());
        }

        $accountId = $record->getIban();

        if ($accountId) {
            $transfer->setAccountId($accountId);
        }

        if ($record->getBic()) {
            $transfer->setProvider($record->getBic());
        }

        return $transfer;
    }

    private function getPaymentMeansCode(?Entity $record): ?string
    {
        $code = null;

        if ($record instanceof PaymentChannelWireTransfer) {
            $code = '30';
        }

        if ($record instanceof PaymentChannelSepaCreditTransfer) {
            $code = '58';
        }

        if ($record instanceof PaymentChannelSepaDirectDebit) {
            $code = '59';
        }

        return $code;
    }

    private function prepareMandate(?Account $account, ?Entity $record, EInvoice $eInvoice): ?Mandate
    {
        if (!$account || !$record instanceof PaymentChannelSepaDirectDebit) {
            return null;
        }

        $mandateRecord = $this->mandateProvider->get($account, PaymentMandateSepa::TYPE);

        if (!$mandateRecord) {
            return null;
        }

        $mandate = new Mandate();

        $mandate
            ->setReference($mandateRecord->getReferenceId())
            ->setAccount($mandateRecord->getIban());

        if ($record->getCreditorIdentifier()) {
            $identifier = new Identifier($record->getCreditorIdentifier(), 'SEPA');

            $eInvoice->getSeller()?->addIdentifier($identifier);
        }

        return $mandate;
    }
}
