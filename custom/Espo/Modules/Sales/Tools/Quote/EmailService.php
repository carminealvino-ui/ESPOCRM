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

namespace Espo\Modules\Sales\Tools\Quote;

use Espo\Core\Acl;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Htmlizer\TemplateRendererFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\TemplateFileManager;
use Espo\Core\Utils\Util;
use Espo\Entities\Attachment;
use Espo\Entities\Email;
use Espo\Entities\EmailTemplate;
use Espo\Entities\Template;
use Espo\Modules\Crm\Entities\Account;
use Espo\Modules\Crm\Entities\Contact;
use Espo\Modules\Crm\Entities\Opportunity;
use Espo\Modules\Sales\Entities\CreditNote;
use Espo\Modules\Sales\Entities\InventoryAdjustment;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Entities\PurchaseOrder;
use Espo\Modules\Sales\Entities\Quote;
use Espo\Modules\Sales\Entities\ReceiptOrder;
use Espo\Modules\Sales\Entities\ReturnOrder;
use Espo\Modules\Sales\Entities\SalesOrder;
use Espo\Modules\Sales\Entities\TransferOrder;
use Espo\Modules\Sales\Tools\Quote\Email\GetAttributesParams;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Tools\EmailTemplate\Data;
use Espo\Tools\EmailTemplate\Params;
use Espo\Tools\EmailTemplate\Processor;
use Espo\Tools\Pdf\Service as PdfService;
use RuntimeException;
use stdClass;

class EmailService
{
    public function __construct(
        private EntityManager $entityManager,
        private Acl $acl,
        private TemplateFileManager $templateFileManager,
        private TemplateRendererFactory $templateRendererFactory,
        private PdfService $pdfService,
        private Config $config,
        private Processor $emailTemplateProcessor,
    ) {}

    /**
     * @throws Forbidden
     * @throws Error
     * @throws NotFound
     * @return array<string, mixed>
     */
    public function getAttributes(
        string $sourceType,
        string $sourceId,
        string $templateId,
        ?GetAttributesParams $params = null,
    ): array {

        $params ??= new GetAttributesParams();

        $order = $this->entityManager->getEntityById($sourceType, $sourceId);
        $template = $this->entityManager->getRDBRepositoryByClass(Template::class)->getById($templateId);

        if (!$order || !$template) {
            throw new NotFound();
        }

        if (
            !$params->skipAcl() && (
                !$this->acl->checkEntityRead($order) ||
                !$this->acl->checkEntityRead($template)
            )
        ) {
            throw new Forbidden();
        }

        $attributes = [];

        $attributes['nameHash'] = (object) [];

        [
            $opportunity,
            $account,
            $billingContact,
            $quote,
            $salesOrder,
            $invoice,
        ] = $this->getEntities($order);

        $toList = [];

        if ($billingContact && $billingContact->getEmailAddress()) {
            $emailAddress = $billingContact->getEmailAddress();

            $toList[] = $emailAddress;
            $attributes['nameHash']->$emailAddress = $billingContact->getName();
        }

        $attributes = $this->applyParent($order, $attributes);

        if ($opportunity && !$params->skipOtherRecipients() && $toList === []) {
            /** @var iterable<Contact> $contacts */
            $contacts = $this->entityManager
                ->getRDBRepository(Opportunity::ENTITY_TYPE)
                ->getRelation($opportunity, 'contacts')
                ->find();

            foreach ($contacts as $itContact) {
                $emailAddress = $itContact->getEmailAddress();

                if (!$emailAddress) {
                    continue;
                }

                $toList[] = $emailAddress;
                $attributes['nameHash']->$emailAddress = $itContact->getName();
            }
        }

        if ($account && $toList === [] && $account->getEmailAddress()) {
            $emailAddress = $account->get('emailAddress');

            $toList[] = $emailAddress;
            $attributes['nameHash']->$emailAddress = $account->getName();
        }

        $attributes['to'] = implode(';', $toList);

        $entityHash = $this->prepareEntityHash(
            opportunity: $opportunity,
            billingContact: $billingContact,
            account: $account,
            quote: $quote,
            salesOrder: $salesOrder,
            invoice: $invoice,
            params: $params,
        );

        $templateApplied = $this->applyTemplate(
            quote: $order,
            attributes: $attributes,
            address: $toList[0] ?? null,
            entityHash: $entityHash,
        );

        $attributes = $this->applyAttachment($order, $template, $attributes);

        $attributes['relatedId'] = $sourceId;
        $attributes['relatedType'] = $sourceType;

        if (!$templateApplied) {
            $this->applyHtmlTemplate($template, $order, $attributes);
        }

        return $attributes;
    }

    private function isParentTypeSupported(string $entityType): bool
    {
        $entityTypeList = $this->entityManager
            ->getDefs()
            ->getEntity(Email::ENTITY_TYPE)
            ->getField('parent')
            ->getParam('entityList') ?? [];

        if (!is_array($entityTypeList)) {
            throw new RuntimeException();
        }

        return in_array($entityType, $entityTypeList);
    }


    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function applyParent(Entity $quote, array $attributes): array
    {
        if ($this->isParentTypeSupported($quote->getEntityType())) {
            $attributes['parentId'] = $quote->getId();
            $attributes['parentType'] = $quote->getEntityType();
            $attributes['parentName'] = $quote->get('name');

            return $attributes;
        }

        $accountId = $quote->get('accountId');

        if ($accountId && $this->isParentTypeSupported(Account::ENTITY_TYPE)) {
            $attributes['parentId'] = $accountId;
            $attributes['parentType'] = Account::ENTITY_TYPE;
            $attributes['parentName'] = $quote->get('accountName');

            return $attributes;
        }

        $opportunityId = $quote->get('opportunityId');

        if (
            $opportunityId &&
            $this->isParentTypeSupported(Opportunity::ENTITY_TYPE) &&
            !$accountId
        ) {
            $attributes['parentId'] = $opportunityId;
            $attributes['parentType'] = Opportunity::ENTITY_TYPE;
            $attributes['parentName'] = $quote->get('opportunityName');
        }

        return $attributes;
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, Entity> $entityHash
     * @throws Error
     */
    private function applyTemplate(
        Entity $quote,
        array &$attributes,
        ?string $address,
        array $entityHash
    ): bool {

        $entityType = $quote->getEntityType();

        $key = match ($entityType) {
            Quote::ENTITY_TYPE => 'salesQuoteEmailTemplate',
            SalesOrder::ENTITY_TYPE => 'salesSalesOrderEmailTemplate',
            Invoice::ENTITY_TYPE => 'salesInvoiceEmailTemplate',
            PurchaseOrder::ENTITY_TYPE => 'salesPurchaseOrderEmailTemplate',
            ReceiptOrder::ENTITY_TYPE => 'salesReceiptOrderEmailTemplate',
            ReturnOrder::ENTITY_TYPE => 'salesReturnOrderEmailTemplate',
            CreditNote::ENTITY_TYPE => 'salesCreditNoteEmailTemplate',
            TransferOrder::ENTITY_TYPE => 'salesTransferOrderEmailTemplate',
            InventoryAdjustment::ENTITY_TYPE => 'salesInventoryAdjustmentEmailTemplate',
            default => null,
        };

        if (!$key) {
            return false;
        }

        $templateId = $this->config->get($key . 'Id');

        if (!$templateId) {
            return false;
        }

        $template = $this->entityManager->getRDBRepositoryByClass(EmailTemplate::class)->getById($templateId);

        if (!$template) {
            throw new Error("Email template does not exist. Admin should change email template in settings.");
        }

        $data = Data::create()
            ->withParent($quote)
            ->withEmailAddress($address)
            ->withEntityHash($entityHash);

        $params = Params::create()
            ->withApplyAcl()
            ->withCopyAttachments();

        $result = $this->emailTemplateProcessor->process($template, $params, $data);

        $attributes['name'] = $result->getSubject();
        $attributes['body'] = $result->getBody();
        $attributes['isHtml'] = $result->isHtml();

        $attachmentsIds = [];
        $attachmentsNames = [];

        foreach ($result->getAttachmentList() as $attachment) {
            $attachmentsIds[] = $attachment->getId();
            $attachmentsNames[$attachment->getId()] = $attachment->getName();
        }

        $attributes['attachmentsIds'] = $attachmentsIds;
        $attributes['attachmentsNames'] = (object) $attachmentsNames;

        return true;
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     * @throws Forbidden
     * @throws NotFound
     * @throws Error
     */
    private function applyAttachment(Entity $quote, ?Template $template, array $attributes): array
    {
        $result = $this->pdfService
            ->generate(
                $quote->getEntityType(),
                $quote->getId(),
                $template->getId(),
            );

        $contents = $result->getString();

        $filename = null;

        if (class_exists("Espo\\Tools\\Pdf\\Result")) {
            $filename = $result->getFilename();
        }

        $filename ??= Util::sanitizeFileName($template->get('name') . ' ' . $quote->get('name')) . '.pdf';

        $attachment = $this->entityManager->getNewEntity(Attachment::ENTITY_TYPE);

        $attachment->setMultiple([
            'name' => $filename,
            'type' => 'application/pdf',
            'role' => Attachment::ROLE_ATTACHMENT,
            'contents' => $contents,
            'parentType' => Email::ENTITY_TYPE,
            'field' => 'attachments',
        ]);

        $this->entityManager->saveEntity($attachment);

        /** @var string[] $ids */
        $ids = $attributes['attachmentsIds'] ?? [];
        /** @var stdClass $names */
        $names = $attributes['attachmentsNames'] ?? (object) [];

        $ids[] = $attachment->getId();
        $names->{$attachment->getId()} = $attachment->get('name');

        $attributes['attachmentsIds'] = $ids;
        $attributes['attachmentsNames'] = $names;

        return $attributes;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function applyHtmlTemplate(
        Template $template,
        Entity $quote,
        array &$attributes
    ): void {
        $renderer = $this->templateRendererFactory->create();

        $data = [];

        $data['templateName'] = $template->get('name');

        $subjectTpl = $this->templateFileManager
            ->getTemplate('salesEmailPdf', 'subject', $quote->getEntityType());

        $bodyTpl = $this->templateFileManager
            ->getTemplate('salesEmailPdf', 'body', $quote->getEntityType());

        $renderer
            ->setApplyAcl()
            ->setEntity($quote)
            ->setData($data);

        $subject = $renderer->renderTemplate($subjectTpl);
        $body = $renderer->renderTemplate($bodyTpl);

        $attributes['name'] = $subject;
        $attributes['body'] = $body;
    }

    /**
     * @return array{?Opportunity, ?Account, ?Contact, ?Quote, ?SalesOrder, ?Invoice}
     */
    private function getEntities(Entity $order): array
    {
        $billingContactId = $order->get('billingContactId');
        $opportunityId = $order->get('opportunityId');
        $accountId = $order->get('accountId');

        $opportunity = $opportunityId ?
            $this->entityManager->getRDBRepositoryByClass(Opportunity::class)->getById($opportunityId) : null;

        $account = $accountId ?
            $this->entityManager->getRDBRepositoryByClass(Account::class)->getById($accountId) : null;

        $billingContact = $billingContactId ?
            $this->entityManager->getRDBRepositoryByClass(Contact::class)->getById($billingContactId) : null;

        $quote = $order instanceof SalesOrder || $order instanceof Invoice ?
            $order->getQuote() : null;

        $salesOrder = $order instanceof Invoice || $order instanceof ReturnOrder ?
            $order->getSalesOrder() : null;

        $invoice = $order instanceof CreditNote ?
            $order->getInvoice() : null;

        return [
            $opportunity,
            $account,
            $billingContact,
            $quote,
            $salesOrder,
            $invoice,
        ];
    }

    /**
     * @return array<string, Entity>
     */
    private function prepareEntityHash(
        ?Opportunity $opportunity,
        ?Contact $billingContact,
        ?Account $account,
        ?Quote $quote,
        ?SalesOrder $salesOrder,
        ?Invoice $invoice,
        GetAttributesParams $params,
    ): array {

        $entityHash = [];

        if ($opportunity) {
            $entityHash[Opportunity::ENTITY_TYPE] = $opportunity;
        }

        if ($billingContact) {
            $entityHash[Contact::ENTITY_TYPE] = $billingContact;
        }

        if ($account) {
            $entityHash[Account::ENTITY_TYPE] = $account;
        }

        if ($quote) {
            $entityHash[Quote::ENTITY_TYPE] = $quote;
        }

        if ($salesOrder) {
            $entityHash[SalesOrder::ENTITY_TYPE] = $salesOrder;
        }

        if ($invoice) {
            $entityHash[Invoice::ENTITY_TYPE] = $invoice;
        }

        foreach ($entityHash as $key => $entity) {
            if (
                !$params->skipAcl() &&
                !$this->acl->checkEntityRead($entity)
            ) {
                unset($entityHash[$key]);
            }
        }

        return $entityHash;
    }
}
