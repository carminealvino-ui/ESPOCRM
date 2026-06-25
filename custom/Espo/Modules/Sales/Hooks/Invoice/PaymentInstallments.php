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

namespace Espo\Modules\Sales\Hooks\Invoice;

use Espo\Core\Hook\Hook\AfterRemove;
use Espo\Core\Hook\Hook\AfterSave;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Tools\PaymentTerms\PaymentInstallmentsSaveProcessor;
use Espo\Modules\Sales\Tools\PaymentTerms\PaymentInstallmentsRemoveProcessor;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\RemoveOptions;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * @implements AfterSave<Invoice>
 * @implements AfterRemove<Invoice>
 */
class PaymentInstallments implements AfterSave, AfterRemove
{
    public static int $order = 10;

    public function __construct(
        private PaymentInstallmentsSaveProcessor $saveProcessor,
        private PaymentInstallmentsRemoveProcessor $removeProcessor,
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        $this->saveProcessor->process($entity);
    }

    public function afterRemove(Entity $entity, RemoveOptions $options): void
    {
        $this->removeProcessor->process($entity);
    }
}
