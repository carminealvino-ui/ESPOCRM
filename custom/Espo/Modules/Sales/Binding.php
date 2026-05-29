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

namespace Espo\Modules\Sales;

use Espo\Core\Binding\Binder;
use Espo\Core\Binding\BindingProcessor;
use Espo\Modules\Sales\Tools\Invoice\EInvoice\DefaultPreparator;
use Espo\Modules\Sales\Tools\Invoice\EInvoice\Preparator;
use Espo\Modules\Sales\Tools\Invoice\ECreditNote\DefaultPreparator as DefaultCreditNotePreparator;
use Espo\Modules\Sales\Tools\Invoice\ECreditNote\Preparator as CreditNotePreparator;
use Espo\Modules\Sales\Tools\Quote\BeforeSaveProcessor;

class Binding implements BindingProcessor
{
    public function process(Binder $binder): void
    {
        $binder->bindImplementation(
            Preparator::class,
            DefaultPreparator::class
        );

        $binder->bindImplementation(
            CreditNotePreparator::class,
            DefaultCreditNotePreparator::class
        );

        $binder->bindService(BeforeSaveProcessor::class, 'salesBeforeSaveProcessor');
    }
}
