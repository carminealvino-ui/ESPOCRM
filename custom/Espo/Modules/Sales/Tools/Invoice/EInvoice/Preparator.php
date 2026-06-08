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

namespace Espo\Modules\Sales\Tools\Invoice\EInvoice;

use Einvoicing\Invoice as EInvoice;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Tools\Invoice\EInvoice\Exceptions\UnknownFormat;

/**
 * Converts an Invoice entity to an E-Invoice instance.
 * A custom implementation can be used for a specific business need.
 * E.g. adding payment instructions from a custom field.
 */
interface Preparator
{
    /**
     * @throws UnknownFormat
     */
    public function prepare(Invoice $invoice, string $format): EInvoice;
}
