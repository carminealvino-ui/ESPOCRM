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

namespace Espo\Modules\Sales\Tools\PaymentRequest\SepaDirectDebit;

use DateTime;
use Digitick\Sepa\Exception\InvalidArgumentException;
use Digitick\Sepa\PaymentInformation;
use Digitick\Sepa\TransferFile\Facade\CustomerDirectDebitFacade;
use Digitick\Sepa\TransferFile\Factory\TransferFileFacadeFactory;
use Espo\Core\Exceptions\Error;
use Espo\Core\FileStorage\Manager;
use Espo\Entities\Attachment;
use Espo\Modules\Sales\Entities\PaymentChannel;
use Espo\Modules\Sales\Entities\PaymentChannelSepaDirectDebit;
use Espo\Modules\Sales\Entities\PaymentMandate;
use Espo\Modules\Sales\Entities\PaymentMandateSepa;
use Espo\Modules\Sales\Entities\PaymentRequest;
use Espo\Modules\Sales\Tools\PaymentMandate\MandateProvider;
use Espo\ORM\EntityManager;
use RuntimeException;

class Service
{
    public function __construct(
        private Manager $fileStorageManager,
        private EntityManager $entityManager,
        private MandateProvider $mandateProvider,
    ) {}

    /**
     * @throws Error
     */
    public function generateXml(PaymentRequest $request): Attachment
    {
        $record = $request->getMethod()->getChannel()?->getRecord();

        if (!$record instanceof PaymentChannelSepaDirectDebit) {
            throw new Error("Not SEPA direct debit channel.");
        }

        if ($request->getMethod()->getChannel()?->getStatus() !== PaymentChannel::STATUS_ACTIVE) {
            throw new Error("Non-active channel.");
        }

        $debit = $this->prepareDebit($request, $record);

        $contents = $debit->asXML();

        $attachment = $this->entityManager->getRDBRepositoryByClass(Attachment::class)->getNew();

        $attachment
            ->setRole(Attachment::ROLE_EXPORT_FILE)
            ->setName($this->getFileName($request))
            ->setSize(strlen($contents))
            ->setType('application/xml');

        $this->entityManager->saveEntity($attachment);

        $this->fileStorageManager->putContents($attachment, $contents);

        return $attachment;
    }

    private function getFileName(PaymentRequest $request): string
    {
        $name = str_replace("\"", "\\\"", $request->getNumber());

        return "SEPA_" . $name . '.xml';
    }

    /**
     * @throws Error
     */
    private function prepareDebit(
        PaymentRequest $request,
        PaymentChannelSepaDirectDebit $record
    ): CustomerDirectDebitFacade {

        $debit = TransferFileFacadeFactory::createDirectDebit($request->getNumber(), $record->getAccountHolder());

        $mandate = $this->getMandate($request);
        $sepaMandate = $mandate->getRecord();

        if (!$sepaMandate instanceof PaymentMandateSepa) {
            throw new RuntimeException();
        }

        try {
            $debit->addPaymentInfo($request->getNumber(), [
                'id' => $request->getNumber(),
                'creditorName' => $record->getAccountHolder(),
                'creditorAccountIBAN' => $record->getIban(),
                'creditorId' => $record->getCreditorIdentifier(),
                'seqType' => PaymentInformation::S_ONEOFF,
                'localInstrumentCode' => strtoupper($sepaMandate->getScheme()),
            ]);

            $remittanceInformation = $this->getRemittanceInformation($request);

            $debit->addTransfer($request->getNumber(), [
                'amount' => (int) ($request->getAmount()->getAmount() * 100),
                'debtorName' => $mandate->getAccountHolder(),
                'debtorIban' => $mandate->getIban(),
                'debtorMandate' => $mandate->getReferenceId(),
                'debtorMandateSignDate' => DateTime::createFromImmutable($mandate->getDateSigned()?->toDateTime()),
                'remittanceInformation' => $remittanceInformation,
            ]);
        } catch (InvalidArgumentException $e) {
            throw new RuntimeException("Invalid arguments.", 0, $e);
        }

        return $debit;
    }

    /**
     * @throws Error
     */
    private function getMandate(PaymentRequest $request): PaymentMandate
    {
        $account = $request->getAccount();

        if (!$account) {
            throw new Error("Payment request has no account.");
        }

        $mandate = $this->mandateProvider->get($account, PaymentMandateSepa::TYPE);

        if (!$mandate || !$mandate->getRecord() instanceof PaymentMandateSepa) {
            throw new Error("SEPA mandate not found for account.");
        }

        return $mandate;
    }

    private function getRemittanceInformation(PaymentRequest $request): string
    {
        $numbers = [];

        foreach ($request->getInvoices() as $invoice) {
            if (!$invoice->getNumber()) {
                continue;
            }

            $numbers[] = $invoice->getNumber();
        }

        if (count($numbers)) {
            return implode(', ', $numbers);
        }

        return $request->getNumber();
    }
}
