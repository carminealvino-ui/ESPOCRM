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

namespace Espo\Modules\Sales\Classes\FieldLoaders\PaymentRequest;

use Espo\Core\FieldProcessing\Loader;
use Espo\Core\FieldProcessing\Loader\Params;
use Espo\Modules\Sales\Classes\FieldLoaders\Invoice\AmountDue;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Entities\PaymentRequest;
use Espo\ORM\Entity;

/**
 * @implements Loader<PaymentRequest>
 */
class InvoiceAmountsLoader implements Loader
{
    private const ATTR_AMOUNT_DUE = Invoice::FIELD_AMOUNT_DUE;

    public function __construct(
        private AmountDue $amountDueLoader,
    ) {}

    public function process(Entity $entity, Params $params): void
    {
        if ($entity->isNotActual()) {
            $entity->setMultiple(['invoiceAmounts' => null]);

            return;
        }

        $map = [];

        foreach ($entity->getInvoices() as $invoice) {
            $this->amountDueLoader->process($invoice, Params::create());

            if ($invoice->get(self::ATTR_AMOUNT_DUE)) {
                $map[$invoice->getId()] = (float) $invoice->get(self::ATTR_AMOUNT_DUE);
            }
        }

        $entity->set('invoiceAmounts', $map);
    }
}
