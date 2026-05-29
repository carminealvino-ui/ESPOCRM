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

namespace Espo\Modules\Sales\Tools\Invoice\EInvoice\Presets;

use Einvoicing\Invoice;
use Einvoicing\Presets\AbstractPreset;

/**
 * @noinspection SpellCheckingInspection
 */
class XRechnung extends AbstractPreset
{
    public function getSpecification(): string
    {
        /** @noinspection SpellCheckingInspection */
        return 'urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0';
    }

    public function getRules(): array
    {
        return [
            'BR-CO-25' => static function (Invoice $invoice) {
                if ($invoice->getDueDate()) {
                    return null;
                }

                if ($invoice->getPayment() && $invoice->getPayment()->getTerms()) {
                    return null;
                }

                if ($invoice->getTotals()->payableAmount <= 0) {
                    return null;
                }

                return "In case the Amount due for payment (BT-115) is positive, either " .
                    "the Payment due date (BT-9) or the Payment terms (BT-20) shall be present.";
            },
        ];
    }

    /**
     * @return void
     */
    public function setupInvoice(Invoice $invoice) {
        parent::setupInvoice($invoice);

        $invoice->setBusinessProcess('urn:fdc:peppol.eu:2017:poacc:billing:01:1.0');
    }
}
