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

namespace Espo\Modules\Sales\Tools\PaymentRequest;

use Espo\Core\Utils\Config;
use Espo\Core\Utils\Language;
use Espo\Entities\EmailTemplate;
use Espo\Modules\Crm\Entities\Account;
use Espo\Modules\Crm\Entities\Contact;
use Espo\Modules\Sales\Entities\PaymentRequest;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Tools\EmailTemplate\Data;
use Espo\Tools\EmailTemplate\Params;
use Espo\Tools\EmailTemplate\Processor as EmailTemplateProcessor;
use Espo\Tools\EmailTemplate\Result;
use LogicException;
use RuntimeException;
use stdClass;

class PrepareEmailAttributesService
{
    public function __construct(
        private EntityManager $entityManager,
        private EmailTemplateProcessor $processor,
        private Language $defaultLanguage,
        private Config $config,
    ) {}

    /**
     * @param Entity[] $entityList
     */
    public function prepare(PaymentRequest $request, array $entityList = []): stdClass
    {
        $template = $this->prepareTemplate($request);

        [$emailAddress, $recipientName, $contact] = $this->getRecipientData($request);

        $params = (new Params())
            ->withCopyAttachments(!$template->isNew())
            ->withApplyAcl(false);

        $data = $this->prepareData($request, $emailAddress, $contact, $entityList);

        $result = $this->processor->process($template, $params, $data);

        $attributes = $result->getValueMap();

        $this->amendAttributes($result, $attributes, $emailAddress, $recipientName, $request);

        return $attributes;
    }

    private function prepareTemplate(PaymentRequest $request): EmailTemplate
    {
        $template = $this->getTemplate();

        $subject = $this->defaultLanguage->translateLabel(PaymentRequest::ENTITY_TYPE, 'scopeNames') . ' ' .
            $request->getNumber();

        $url = $request->getPaymentUrl();

        if (!$url) {
            throw new RuntimeException("No payment URL.");
        }

        $linkLabel = $this->getLinkLabel();

        if ($template->isNew()) {
            $body = "<p><a href=\"$url\">$linkLabel</a></p>";

            $template->set('subject', $subject);
            $template->set('isHtml', true);
            $template->set('body', $body);
        }

        return $template;
    }

    /**
     * @param Entity[] $entityList
     */
    private function prepareData(
        PaymentRequest $request,
        ?string $emailAddress,
        ?Contact $contact,
        array $entityList = []
    ): Data {

        $entityHash = [];

        if ($request->getAccount()) {
            $entityHash[Account::ENTITY_TYPE] = $request->getAccount();
        }

        if ($contact) {
            $entityHash[Contact::ENTITY_TYPE] = $contact;
        }

        foreach ($entityList as $entity) {
            $entityHash[$entity->getEntityType()] = $entity;
        }

        return (new Data())
            ->withParent($request)
            ->withEmailAddress($emailAddress)
            ->withEntityHash($entityHash);
    }

    private function amendAttributes(
        Result $result,
        stdClass $attributes,
        ?string $emailAddress,
        ?string $recipientName,
        PaymentRequest $request,
    ): void {

        $attributes->name = $attributes->subject ?? null;

        if ($emailAddress) {
            $attributes->to = $emailAddress;

            $attributes->nameHash = (object) [
                $emailAddress => $recipientName,
            ];
        }

        $body = $attributes->body ?? '';

        if (!is_string($body)) {
            throw new LogicException();
        }

        if (
            $result->isHtml() &&
            $request->getPaymentUrl() &&
            !str_contains($body, $request->getPaymentUrl())
        ) {
            $url = $request->getPaymentUrl();
            $linkLabel = $this->getLinkLabel();

            $body .= "<p><a href=\"$url\">$linkLabel</a></p>";

            $attributes->body = $body;
        }

        if ($request->getAccount()) {
            $attributes->parentId = $request->getAccount()->getId();
            $attributes->parentType = Account::ENTITY_TYPE;
            $attributes->parentName = $request->getAccount()->getName();
        }
    }

    /**
     * @return array{?string, ?string, ?Contact}
     */
    private function getRecipientData(PaymentRequest $request): array
    {
        $foundAddress = null;
        $contact = null;

        foreach ($request->getInvoices() as $invoice) {
            $itemContact = $invoice->getBillingContact();

            if (
                !$itemContact ||
                !$itemContact->getEmailAddress() ||
                ($foundAddress && $foundAddress !== $itemContact->getEmailAddress())
            ) {
                $contact = null;

                break;
            }

            $foundAddress = $itemContact->getEmailAddress();
            $contact = $itemContact;
        }

        if ($contact && $contact->getEmailAddress()) {
            return [$contact->getEmailAddress(), $contact->getName(), $contact];
        }

        $account = $request->getAccount();

        if ($account && $account->getEmailAddress()) {
            return [$account->getEmailAddress(), $account->getName(), null];
        }

        return [null, null, null];
    }

    private function getTemplate(): EmailTemplate
    {
        $templateId = $this->config->get('salesPaymentRequestEmailTemplateId');

        if ($templateId) {
            $template = $this->entityManager->getRDBRepositoryByClass(EmailTemplate::class)->getById($templateId);

            if (!$template) {
                throw new RuntimeException("Email template not found. Change template in settings.");
            }

            return $template;
        }

        return $this->entityManager->getRDBRepositoryByClass(EmailTemplate::class)->getNew();
    }

    private function getLinkLabel(): string
    {
        return $this->defaultLanguage->translateLabel('paymentLink', 'strings', PaymentRequest::ENTITY_TYPE);
    }
}
