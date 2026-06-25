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

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ApplicationConfig;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Metadata;
use Espo\Modules\Crm\Entities\Account;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Entities\PaymentChannel;
use Espo\Modules\Sales\Entities\PaymentMethod;
use Espo\Modules\Sales\Entities\PaymentRequest;
use Espo\Modules\Sales\Tools\PaymentRequest\PaymentLink\Params;
use LogicException;
use stdClass;

class PaymentLinkService
{
    public function __construct(
        private Metadata $metadata,
        private Language $defaultLanguage,
        private InjectableFactory $injectableFactory,
        private ApplicationConfig $applicationConfig,
        private Config $config,
    ) {}

    /**
     * @return array<string, mixed>
     * @throws Forbidden
     */
    public function getData(PaymentRequest $request, Params $params): array
    {
        if ($request->getMethod()->getStatus() !== PaymentMethod::STATUS_ACTIVE) {
            throw new Forbidden("Not active payment method.");
        }

        if (
            $request->getMethod()->getChannel() &&
            $request->getMethod()->getChannel()->getStatus() !== PaymentChannel::STATUS_ACTIVE
        ) {
            throw new Forbidden("Not active payment channel.");
        }

        return [
            'id' => $request->getReferenceId(),
            'number' => $request->getNumber(),
            'status' => $request->getStatus(),
            'statusText' => $this->getTranslatedStatus($request),
            'instructions' => $this->getInstructions($request, $params),
            'flowText' => $this->getFlowText($request, $params),
            'flowTextStyle' => $params->flow === Params::FLOW_CHECKOUT_SUCCESS ? 'success' : null,
            'channelView' => $this->getChannelView($request, $params),
            'channelData' => $this->getChannelData($request, $params),
            'amount' => $request->getAmount()->getAmount(),
            'amountCurrency' => $request->getAmount()->getCode(),
            'methodName' => $request->getMethod()->getName(),
            'account' => (object) [
                'name' => $request->getAccount()?->getName(),
                'emailAddress' => $request->getAccount()?->getEmailAddress(),
                'billingAddressCountry' => $request->getAccount()?->getBillingAddress()?->getCountry(),
            ],
            'invoices' => $this->getInvoicesData($request),
            'labels' => $this->getLabels(),
            'expirationDate' => $this->getExpirationDate($request),
            'config' => [
                'dateFormat' => $this->applicationConfig->getDateFormat(),
                'currencyDecimalPlaces' => $this->config->get('currencyDecimalPlaces'),
                'thousandSeparator' => $this->config->get('thousandSeparator'),
                'decimalMark' => $this->config->get('decimalMark'),
            ],
        ];
    }

    private function getTranslatedStatus(PaymentRequest $request): string
    {
        return $this->defaultLanguage->translateOption($request->getStatus(), 'status', PaymentRequest::ENTITY_TYPE);
    }

    private function getInstructions(PaymentRequest $request, Params $params): ?string
    {
        if ($params->flow === Params::FLOW_CHECKOUT_SUCCESS) {
            return null;
        }

        $text = $request->getMethod()->getInstructions();

        if (!$text) {
            return null;
        }

        $invoiceNumbers = [];

        foreach ($request->getInvoices() as $invoice) {
            if ($invoice->getNumber()) {
                $invoiceNumbers[] = $invoice->getNumber();
            }
        }

        $invoiceNumbersText = implode(', ', $invoiceNumbers);

        $text = str_replace('{invoiceNumbers}', $invoiceNumbersText, $text);

        return str_replace('{number}', $request->getNumber(), $text);
    }

    private function getChannelData(PaymentRequest $request, Params $params): ?stdClass
    {
        if ($params->flow === Params::FLOW_CHECKOUT_SUCCESS) {
            return null;
        }

        if (
            $request->isNotActual() ||
            !in_array($request->getStatus(), [
                PaymentRequest::STATUS_DRAFT,
                PaymentRequest::STATUS_PENDING,
            ])
        ) {
            return null;
        }

        $method = $request->getMethod();
        $channel = $method->getChannel();

        if (!$channel) {
            return null;
        }

        $provider = $channel->getProvider();

        /** @var ?class-string $className */
        $className = $this->metadata->get("app.salesPaymentProviders.$provider.paymentLink.dataProviderClassName");

        if (!$className) {
            return (object) [];
        }

        $dataProvider = $this->injectableFactory->create($className);

        if (!$dataProvider instanceof PaymentLinkDataProvider) {
            throw new LogicException();
        }

        return $dataProvider->get($request);
    }

    private function getChannelView(PaymentRequest $request, Params $params): ?string
    {
        if ($params->flow === Params::FLOW_CHECKOUT_SUCCESS) {
            return null;
        }

        if ($request->isNotActual()) {
            return null;
        }

        $method = $request->getMethod();
        $channel = $method->getChannel();

        if (!$channel) {
            return null;
        }

        $provider = $channel->getProvider();

        return $this->metadata->get("app.salesPaymentProviders.$provider.paymentLink.view");
    }

    /**
     * @return stdClass[]
     */
    private function getInvoicesData(PaymentRequest $request): array
    {
        $output = [];

        foreach ($request->getInvoices() as $invoice) {
            $invoice->loadItemListField();

            $items = [];

            foreach ($invoice->getItems() as $item) {
                $items[] = (object) [
                    'name' => $item->getName() ?? $item->getProductName(),
                    'quantity' => $item->getQuantity(),
                ];
            }

            $output[] = (object) [
                'number' => $invoice->getNumber(),
                'items' => $items,
            ];
        }

        return $output;
    }

    /**
     * @return array<string, mixed>
     */
    private function getLabels(): array
    {
        $language = $this->defaultLanguage;

        return [
            'proceed' => $language->translateLabel('proceed', 'strings', PaymentRequest::ENTITY_TYPE),
            'method' => $language->translateLabel('method', 'fields', PaymentRequest::ENTITY_TYPE),
            'invoice' => $language->translateLabel(Invoice::ENTITY_TYPE, 'scopeNames'),
            'instructions' => $language->translateLabel('instructions', 'fields', PaymentMethod::ENTITY_TYPE),
            'number' => $language->translateLabel('number', 'fields', PaymentRequest::ENTITY_TYPE),
            'paymentRequest' => $language->translateLabel(PaymentRequest::ENTITY_TYPE, 'scopeNames'),
            'status' => $language->translateLabel('status', 'fields', PaymentRequest::ENTITY_TYPE),
            'amount' => $language->translateLabel('amount', 'fields', PaymentRequest::ENTITY_TYPE),
            'expirationDate' => $language->translateLabel('expirationDate', 'fields', PaymentRequest::ENTITY_TYPE),
            'account' => $language->translateLabel(Account::ENTITY_TYPE, 'scopeNames'),
            'accountCountry' => $language->translateLabel('billingAddressCountry', 'fields', Account::ENTITY_TYPE),
            'draftMessage' => $language->translateLabel('draftMessage', 'messages', PaymentRequest::ENTITY_TYPE),
        ];
    }

    private function getExpirationDate(PaymentRequest $request): ?string
    {
        if (!$request->getExpirationDate()) {
            return null;
        }

        return $request->getExpirationDate()->toString();
    }

    private function getFlowText(PaymentRequest $request, Params $params): ?string
    {
        if ($params->flow === Params::FLOW_CHECKOUT_SUCCESS) {
            if (
                in_array($request->getStatus(), [
                    PaymentRequest::STATUS_PENDING,
                    PaymentRequest::STATUS_IN_PROGRESS,
                ])
            ) {
                return $this->defaultLanguage
                    ->translateLabel('checkoutSuccessInProcess', 'messages', PaymentRequest::ENTITY_TYPE);
            }

            if ($request->getStatus() === PaymentRequest::STATUS_PAID) {
                return $this->defaultLanguage
                    ->translateLabel('checkoutSuccessPaid', 'messages', PaymentRequest::ENTITY_TYPE);
            }
        }

        return null;
    }
}
