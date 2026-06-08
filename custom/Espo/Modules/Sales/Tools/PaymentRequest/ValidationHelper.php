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

use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Utils\Metadata;
use Espo\Entities\User;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Entities\PaymentChannelSepaDirectDebit;
use Espo\Modules\Sales\Entities\PaymentMethod;
use Espo\Modules\Sales\Entities\PaymentRequest;
use Espo\ORM\EntityManager;

class ValidationHelper
{
    public function __construct(
        private EntityManager $entityManager,
        private Metadata $metadata,
        private User $user,
    ) {}

    /**
     * @throws Forbidden
     */
    public function validate(PaymentRequest $request): void
    {
        $this->processStatus($request);
        $this->processFieldsAutomated($request);
        $this->processFields($request);
        $this->processInvoices($request);
        $this->validateMethod($request);
        $this->validateChannel($request);
    }

    /**
     * @throws Forbidden
     */
    private function processInvoices(PaymentRequest $request): void
    {
        if (!$request->isAttributeChanged('invoicesIds') && !$request->isAttributeChanged('accountId')) {
            return;
        }

        $invoiceIds = $request->getLinkMultipleIdList('invoices');

        $invoices = $this->entityManager
            ->getRDBRepositoryByClass(Invoice::class)
            ->where(['id' => $invoiceIds])
            ->find();

        $prevCode = null;

        foreach ($invoices as $invoice) {
            if ($invoice->isNotActual()) {
                throw Forbidden::createWithBody(
                    'invoiceNotActual',
                    Body::create()
                        ->withMessageTranslation('invoiceNotActual', PaymentRequest::ENTITY_TYPE)
                );
            }

            if ($invoice->getAmount()) {
                if ($prevCode && $invoice->getAmount()->getCode() !== $prevCode) {
                    throw Forbidden::createWithBody(
                        'invoicesCurrencyCodeMismatch',
                        Body::create()
                            ->withMessageTranslation('invoicesCurrencyCodeMismatch', PaymentRequest::ENTITY_TYPE)
                    );
                }

                $prevCode = $invoice->getAmount()->getCode();
            }

            if (
                $invoice->getAmount() &&
                $request->getAmount()->getCode() !== $invoice->getAmount()->getCode()
            ) {
                throw Forbidden::createWithBody(
                    'currencyCodeMismatch',
                    Body::create()
                        ->withMessageTranslation('currencyCodeMismatch', PaymentRequest::ENTITY_TYPE)
                );
            }

            if (
                $request->getAccount() &&
                $invoice->getAccount()?->getId() !== $request->getAccount()->getId()
            ) {
                throw Forbidden::createWithBody(
                    'accountMismatch',
                    Body::create()
                        ->withMessageTranslation('accountMismatch', PaymentRequest::ENTITY_TYPE)
                );
            }
        }
    }

    /**
     * @throws Forbidden
     */
    private function processStatus(PaymentRequest $request): void
    {
        if (!$request->isAttributeChanged('status')) {
            return;
        }

        if ($request->getStatus() === PaymentRequest::STATUS_EXPIRED) {
            throw Forbidden::createWithBody(
                'cannotSetExpired',
                Body::create()
                    ->withMessageTranslation('cannotSetExpired', PaymentRequest::ENTITY_TYPE)
            );
        }

        if (
            $this->getAutomationType($request) !== 'Full' ||
            $this->user->isAdmin()
        ) {
            return;
        }

        if (
            in_array($request->getPreviousStatus(), [
                PaymentRequest::STATUS_IN_PROGRESS,
                PaymentRequest::STATUS_PAID,
                PaymentRequest::STATUS_CANCELED,
                PaymentRequest::STATUS_EXPIRED,
            ])
        ) {
            throw Forbidden::createWithBody(
                'cannotChangeStatusForAutomatedChannel',
                Body::create()
                    ->withMessageTranslation('cannotChangeStatusForAutomatedChannel', PaymentRequest::ENTITY_TYPE)
            );
        }

        if (
            !in_array($request->getStatus(), [
                PaymentRequest::STATUS_DRAFT,
                PaymentRequest::STATUS_PENDING,
                PaymentRequest::STATUS_CANCELED,
            ])
        ) {
            throw Forbidden::createWithBody(
                'cannotChangeStatusForAutomatedChannel',
                Body::create()
                    ->withMessageTranslation('cannotChangeStatusForAutomatedChannel', PaymentRequest::ENTITY_TYPE)
            );
        }
    }

    private function getAutomationType(PaymentRequest $request): ?string
    {
        $provider = $request->getMethod()
            ->getChannel()
            ?->getProvider();

        if (!$provider) {
            return null;
        }

        return $this->metadata->get("app.salesPaymentProviders.$provider.automationType");
    }

    /**
     * @throws Forbidden
     */
    private function processFieldsAutomated(PaymentRequest $request): void
    {
        // This logic is excessive as the dynamic logic imposes the same restrictions.

        if (
            $this->getAutomationType($request) !== 'Full' ||
            $this->user->isAdmin()
        ) {
            return;
        }

        if (
            in_array($request->getStatus(), [
                PaymentRequest::STATUS_DRAFT,
                PaymentRequest::STATUS_PENDING,
            ])
        ) {
            return;
        }

        if (
            $request->isAttributeChanged('amount') ||
            $request->isAttributeChanged('amountCurrency')
        ) {
            throw Forbidden::createWithBody(
                'cannotChangeAmountAutomatedChannel',
                Body::create()
                    ->withMessageTranslation('cannotChangeAmountAutomatedChannel', PaymentRequest::ENTITY_TYPE)
            );
        }

        if (
            $request->isAttributeChanged('expirationDate')
        ) {
            throw Forbidden::createWithBody(
                'cannotChangeExpirationDateAutomatedChannel',
                Body::create()
                    ->withMessageTranslation('cannotChangeAmountAutomatedChannel', PaymentRequest::ENTITY_TYPE)
            );
        }
    }

    /**
     * @throws Forbidden
     */
    private function processFields(PaymentRequest $request): void
    {
        // This logic is excessive as the dynamic logic imposes the same restrictions.

        if ($this->user->isAdmin()) {
            return;
        }

        if (
            in_array($request->getStatus(), [
                PaymentRequest::STATUS_DRAFT,
                PaymentRequest::STATUS_PENDING,
            ])
        ) {
            return;
        }

        if (
            $request->isAttributeChanged('methodId')
        ) {
            throw Forbidden::createWithBody(
                'cannotChangeMethod',
                Body::create()
                    ->withMessageTranslation('cannotChangeMethod', PaymentRequest::ENTITY_TYPE)
            );
        }
    }

    /**
     * @throws Forbidden
     */
    private function validateMethod(PaymentRequest $request): void
    {
        if (!$request->isAttributeChanged('methodId')) {
            return;
        }

        $method = $request->getMethod();

        if ($method->getStatus() === PaymentMethod::STATUS_ACTIVE) {
            return;
        }

        throw Forbidden::createWithBody(
            'methodIsNotActive',
            Body::create()
                ->withMessageTranslation('methodIsNotActive', PaymentRequest::ENTITY_TYPE)
        );
    }

    /**
     * @throws Forbidden
     */
    private function validateChannel(PaymentRequest $request): void
    {
        if (!$request->isAttributeChanged('methodId')) {
            return;
        }

        $channel = $request->getMethod()->getChannel();

        if (!$channel) {
            return;
        }

        if ($channel->getRecord() instanceof PaymentChannelSepaDirectDebit) {
            if ($request->getAmount()->getCode() !== 'EUR') {
                throw Forbidden::createWithBody(
                    'currencyNotAllowedForChannel',
                    Body::create()
                        ->withMessageTranslation('currencyNotAllowedForChannel', PaymentRequest::ENTITY_TYPE)
                );
            }
        }
    }
}
