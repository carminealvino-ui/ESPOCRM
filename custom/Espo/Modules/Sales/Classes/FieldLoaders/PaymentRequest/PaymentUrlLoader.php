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
use Espo\Core\Utils\Config\ApplicationConfig;
use Espo\Modules\Sales\Entities\PaymentRequest;
use Espo\ORM\Entity;

/**
 * @implements Loader<PaymentRequest>
 */
class PaymentUrlLoader implements Loader
{
    public function __construct(
        private ApplicationConfig $config,
    ) {}

    public function process(Entity $entity, Params $params): void
    {
        $url = $entity->getReferenceId() ?
            $this->config->getSiteUrl() . '?entryPoint=payment&id=' . $entity->getReferenceId() :
            null;

        $entity->set('paymentUrl', $url);
    }
}
