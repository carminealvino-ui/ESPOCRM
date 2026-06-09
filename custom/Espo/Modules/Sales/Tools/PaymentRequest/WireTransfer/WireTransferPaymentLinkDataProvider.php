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

namespace Espo\Modules\Sales\Tools\PaymentRequest\WireTransfer;

use Espo\Core\Utils\Language;
use Espo\Modules\Sales\Entities\PaymentChannelWireTransfer;
use Espo\Modules\Sales\Entities\PaymentRequest;
use Espo\Modules\Sales\Tools\PaymentRequest\PaymentLinkDataProvider;
use RuntimeException;
use stdClass;

/**
 * @noinspection PhpUnused
 */
class WireTransferPaymentLinkDataProvider implements PaymentLinkDataProvider
{
    public function __construct(
        private Language $defaultLanguage,
    ) {}

    public function get(PaymentRequest $request): stdClass
    {
        $record = $request->getMethod()->getChannel()?->getRecord();

        if (!$record instanceof PaymentChannelWireTransfer) {
            throw new RuntimeException("Not a Wire Transfer record.");
        }

        return (object) [
            'languageData' => $this->getLanguageData(),
            'values' => $this->getValues($record),
        ];
    }

    private function getLanguageData(): stdClass
    {
        $fields = $this->defaultLanguage->get(PaymentChannelWireTransfer::ENTITY_TYPE . '.fields') ?? (object) [];

        return (object) [
            'fields' => $fields,
        ];
    }

    private function getValues(PaymentChannelWireTransfer $record): stdClass
    {
        return (object) [
            'accountHolder' => $record->getAccountHolder(),
            'iban' => $record->getIban(),
            'accountNumber' => $record->getAccountNumber(),
            'bankName' => $record->getBankName(),
            'bankCode' => $record->getBankCode(),
            'bic' => $record->getBic(),
            'bankAddressStreet' => $record->getBankAddress()?->getStreet(),
            'bankAddressCity' => $record->getBankAddress()?->getCity(),
            'bankAddressState' => $record->getBankAddress()?->getState(),
            'bankAddressPostalCode' => $record->getBankAddress()?->getPostalCode(),
            'bankAddressCountry' => $record->getBankAddress()?->getCountry(),
        ];
    }
}
