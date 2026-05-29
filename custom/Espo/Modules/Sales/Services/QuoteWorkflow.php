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

namespace Espo\Modules\Sales\Services;

use Espo\Modules\Sales\Tools\Quote\Email\GetAttributesParams;
use Espo\Modules\Sales\Tools\Quote\EmailService;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\Tools\EmailTemplate\Data as EmailTemplateData;
use Espo\Tools\EmailTemplate\Params as EmailTemplateParams;
use Espo\Tools\EmailTemplate\Service as EmailTemplateService;
use Espo\Core\Currency\ConfigDataProvider;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\InjectableFactory;
use Espo\Core\Mail\EmailSender;
use Espo\Core\Mail\Exceptions\SendingError;
use Espo\Core\Utils\Config;
use Espo\Entities\Attachment;
use Espo\Entities\Email;
use Espo\Entities\Template;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Tools\Currency\Conversion\EntityConverterFactory;

use Laminas\Mail\Message;

use RuntimeException;

/**
 * @deprecated A legacy. For bc.
 */
class QuoteWorkflow
{
    private EntityManager $entityManager;
    private Config $config;
    private EmailSender $emailSender;
    private InjectableFactory $injectableFactory;
    private EmailTemplateService $emailTemplateService;

    public function __construct(
        EntityManager $entityManager,
        Config $config,
        EmailSender $emailSender,
        InjectableFactory $injectableFactory,
        EmailTemplateService $emailTemplateService
    ) {
        $this->entityManager = $entityManager;
        $this->config = $config;
        $this->emailSender = $emailSender;
        $this->injectableFactory = $injectableFactory;
        $this->emailTemplateService = $emailTemplateService;
    }

    /**
     * @noinspection PhpUnusedParameterInspection
     * @noinspection PhpUnused
     * @phpstan-ignore-next-line
     */
    public function addItemList(string $workflowId, Entity $entity, $data): void
    {
        if (is_array($data)) {
            $data = (object) $data;
        }

        if (!isset($data->itemList) || !is_array($data->itemList)) {
            throw new RuntimeException('Bad itemList provided in addQuoteItemList.');
        }

        if (empty($data->itemList)) {
            return;
        }

        $newItemList = $data->itemList;

        /** @var OrderEntity $entity */
        $entity = $this->entityManager->getEntityById($entity->getEntityType(), $entity->getId());

        if (!$entity->has(OrderEntity::ATTR_ITEM_LIST)) {
            $entity->loadItemListField();
        }

        $itemList = $entity->get(OrderEntity::ATTR_ITEM_LIST);

        foreach ($newItemList as $item) {
            $itemList[] = (object) $item;
        }

        $entity->set(OrderEntity::ATTR_ITEM_LIST, $itemList);

        if (!$entity->has('modifiedById')) {
            $entity->set('modifiedByName', 'System');
        }

        $this->entityManager->saveEntity($entity, [
            'skipWorkflow' => true,
            'modifiedById' => 'system',
            'addItemList' => true,
        ]);
    }

    /**
     * @noinspection PhpUnused
     * @noinspection PhpUnusedParameterInspection
     * @phpstan-ignore-next-line
     */
    public function convertCurrency(string $workflowId, Entity $entity, $data)
    {
        if (!class_exists(EntityConverterFactory::class)) {
            throw new RuntimeException("Convert currency service action requires EspoCRM v7.5 or greater.");
        }

        $targetCurrency = $data->targetCurrency ?? $this->config->get('defaultCurrency');

        $converter = $this->injectableFactory
            ->create(EntityConverterFactory::class)
            ->create($entity->getEntityType());

        $rates = $this->injectableFactory
            ->create(ConfigDataProvider::class)
            ->getCurrencyRates();

        $converter->convert($entity, $targetCurrency, $rates);

        $this->entityManager->saveEntity($entity, [
            'skipWorkflow' => true,
            'modifiedById' => 'system',
            'addItemList' => true,
            'forceIsNotNew' => true,
        ]);
    }

    /**
     * @throws Forbidden
     * @throws Error
     * @throws NotFound
     * @throws SendingError
     * @noinspection PhpUnused
     * @noinspection PhpUnusedParameterInspection
     * @phpstan-ignore-next-line
     */
    public function sendInEmail(string $workflowId, Entity $entity, $data): void
    {
        $templateId = $data->templateId ?? null;
        $emailTemplateId = $data->emailTemplateId ?? null;

        if (!$templateId) {
            throw new RuntimeException("QuoteWorkflow sendInEmail: No templateId");
        }

        $template = $this->entityManager->getEntityById(Template::ENTITY_TYPE, $templateId);

        if (!$template) {
            throw new RuntimeException("QuoteWorkflow sendInEmail: Template doesn't exist");
        }

        $attributes = $this->injectableFactory
            ->create(EmailService::class)
            ->getAttributes($entity->getEntityType(), $entity->getId(), $templateId, new GetAttributesParams(true));

        if ($emailTemplateId) {
            $emailTemplateData = EmailTemplateData::create()
                ->withEntityHash([$entity->getEntityType() => $entity]);

            $emailTemplateParams = EmailTemplateParams::create()
                ->withCopyAttachments();

            $emailTemplateResult = $this->emailTemplateService
                ->process($emailTemplateId, $emailTemplateData, $emailTemplateParams);

            $attributes['name'] = $emailTemplateResult->getSubject();
            $attributes['body'] = $emailTemplateResult->getBody();
            $attributes['isHtml'] = $emailTemplateResult->isHtml();

            foreach ($emailTemplateResult->getAttachmentIdList() as $attachmentId) {
                $attributes['attachmentsIds'][] = $attachmentId;
            }
        }

        $to = $data->to ?? null;

        if ($to && str_starts_with($to, 'link:')) {
            $linkPath = substr($to, 5);
            $arr = explode('.', $linkPath);
            $target = $entity;

            foreach ($arr as $link) {
                $linkType = $target->getRelationType($link);

                if (
                    $linkType !== Entity::BELONGS_TO &&
                    $linkType !== Entity::BELONGS_TO_PARENT &&
                    $linkType !== Entity::HAS_ONE
                ) {
                    throw new Error("QuoteWorkflow sendInEmail: Bad TO link");
                }

                $target = $target->get($link);

                if (!$target) {
                    throw new Error("QuoteWorkflow sendInEmail: Could not find TO recipient");
                }
            }

            $emailAddress = $target->get('emailAddress');

            if (!$emailAddress) {
                throw new Error("QuoteWorkflow sendInEmail: Recipient doesn't have email address");
            }

            $attributes['to'] = $emailAddress;
        }

        if (empty($attributes['to'])) {
            throw new Error("QuoteWorkflow sendInEmail: Not recipient found");
        }

        /** @var Email $email */
        $email = $this->entityManager->getNewEntity(Email::ENTITY_TYPE);

        $email->set($attributes);

        $attachmentList = [];

        foreach ($attributes['attachmentsIds'] as $attachmentId) {
            /** @var ?Attachment $attachment */
            $attachment = $this->entityManager->getEntityById(Attachment::ENTITY_TYPE, $attachmentId);

            if ($attachment) {
                $attachmentList[] = $attachment;
            }
        }

        $this->emailSender
            ->withAttachments($attachmentList)
            ->send($email);

        $this->entityManager->saveEntity($email);
    }
}
