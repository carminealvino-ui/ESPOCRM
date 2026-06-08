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

namespace Espo\Modules\Sales\Tools\Invoice;

use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Entities\Attachment;
use Espo\Entities\Email;
use Espo\Modules\Sales\Entities\CreditNote;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Tools\Invoice\EInvoice\UblGenerator;
use Espo\Modules\Sales\Tools\Quote\Email\GetAttributesParams;
use Espo\Modules\Sales\Tools\Quote\EmailService as BaseEmailService;
use Espo\ORM\EntityManager;
use LogicException;

class EmailService
{
    public function __construct(
        private BaseEmailService $baseEmailService,
        private UblGenerator $ublGenerator,
        private EntityManager $entityManager
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
        ?string $format = null,
    ): array {

        $attributes = $this->baseEmailService->getAttributes($sourceType, $sourceId, $templateId, $params);

        if ($format) {
            $attributes = $this->addEInvoice(
                sourceType: $sourceType,
                sourceId: $sourceId,
                format: $format,
                attributes: $attributes,
            );
        }

        return $attributes;
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     * @throws Error
     * @throws NotFound
     */
    private function addEInvoice(string $sourceType, string $sourceId, string $format, array $attributes): array
    {
        $invoice = $this->entityManager->getRDBRepository($sourceType)->getById($sourceId);

        if (!$invoice) {
            throw new NotFound();
        }

        if (
            !$invoice instanceof Invoice &&
            !$invoice instanceof CreditNote
        ) {
            throw new LogicException("Wrong entity type.");
        }

        try {
            $attachment = $this->ublGenerator->generateAttachment($invoice, $format);
        } catch (EInvoice\Exceptions\RuleValidationFailure $e) {
            throw Error::createWithBody(
                $e->getMessage(),
                Error\Body::create()
                    ->withMessageTranslation('ublRuleValidationFailure', Invoice::ENTITY_TYPE, [
                        'ruleId' => $e->getRuleId(),
                        'message' => $e->getMessage(),
                    ])
            );
        } catch (EInvoice\Exceptions\UnknownFormat) {
            throw new Error("Unknown format.");
        } catch (EInvoice\Exceptions\UnsupportedTaxCombination $e) {
            throw new Error("Unsupported tax combination.", previous:  $e);
        }

        $attachment
            ->setRole(Attachment::ROLE_ATTACHMENT)
            ->setType('application/xml')
            ->setTargetField('attachments')
            ->set('parentType', Email::ENTITY_TYPE);

        $this->entityManager->saveEntity($attachment);

        $attributes['attachmentsIds'] ??= [];
        $attributes['attachmentsNames'] ??= (object)[];

        $attributes['attachmentsIds'][] = $attachment->getId();
        $attributes['attachmentsNames']->{$attachment->getId()} = $attachment->getName();

        return $attributes;
    }
}
