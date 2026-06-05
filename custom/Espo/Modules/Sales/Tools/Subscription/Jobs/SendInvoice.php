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

namespace Espo\Modules\Sales\Tools\Subscription\Jobs;

use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Job\Job;
use Espo\Core\Job\Job\Data;
use Espo\Core\Mail\EmailSender;
use Espo\Core\Mail\Exceptions\SendingError;
use Espo\Core\Utils\Config;
use Espo\Entities\Email;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Tools\Invoice\EmailService;
use Espo\Modules\Sales\Tools\Quote\Email\GetAttributesParams;
use Espo\ORM\EntityManager;
use RuntimeException;

class SendInvoice implements Job
{
    public function __construct(
        private EntityManager $entityManager,
        private EmailService $emailService,
        private EmailSender $emailSender,
        private Config $config,
    ) {}

    public function run(Data $data): void
    {
        $invoice = $this->getInvoice($data);

        $email = $this->entityManager->getRDBRepositoryByClass(Email::class)->getNew();

        $this->applyEmailAttributes($invoice, $email);

        $this->entityManager->saveEntity($email);

        // @todo Apply email account SMTP.

        try {
            $this->emailSender->send($email);
        } catch (SendingError $e) {
            throw new RuntimeException("Could not send invoice {$invoice->getId()}.", 0, $e);
        }
    }

    private function getInvoice(Data $data): Invoice
    {
        $id = $data->getTargetId();

        if (!$id) {
            throw new RuntimeException("No ID.");
        }

        $request = $this->entityManager->getRDBRepositoryByClass(Invoice::class)->getById($id);

        if (!$request) {
            throw new RuntimeException("Invoice $id not found.");
        }

        return $request;
    }

    private function applyEmailAttributes(Invoice $invoice, Email $email): void
    {
        $templateId = $this->config->get('salesInvoiceTemplateId');

        if (!$templateId) {
            throw new RuntimeException("Could not send invoice. No invoice PDF template in config.");
        }

        $eInvoiceFormat = $this->config->get('eInvoiceFormat');

        try {
            $attributes = $this->emailService->getAttributes(
                sourceType: $invoice->getEntityType(),
                sourceId: $invoice->getId(),
                templateId: $templateId,
                params: new GetAttributesParams(
                    skipOtherRecipients: true,
                    skipAcl: true,
                ),
                format: $eInvoiceFormat,
            );
        } catch (Error|Forbidden|NotFound $e) {
            throw new RuntimeException("Could not prepare email for invoice {$invoice->getId()}.", 0, $e);
        }

        $email->setMultiple($attributes);
    }
}
