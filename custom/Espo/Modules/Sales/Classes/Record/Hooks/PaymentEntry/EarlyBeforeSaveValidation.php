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

namespace Espo\Modules\Sales\Classes\Record\Hooks\PaymentEntry;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Record\Hook\SaveHook;
use Espo\Modules\Sales\Entities\PaymentEntry;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\ORM\Entity;

/**
 * @implements SaveHook<PaymentEntry>
 */
class EarlyBeforeSaveValidation implements SaveHook
{
    public function __construct() {}

    public function process(Entity $entity): void
    {
        $this->validateStatus($entity);
    }

    /**
     * @throws BadRequest
     */
    private function validateStatus(PaymentEntry $entity): void
    {
        if (!$entity->isAttributeChanged(OrderEntity::FIELD_STATUS)) {
            return;
        }

        if (
            $entity->getStatus() === PaymentEntry::STATUS_PAID ||
            $entity->getStatus() === PaymentEntry::STATUS_COMPLETED
        ) {
            return;
        }

        if ($entity->getAllocations() === []) {
            return;
        }

        throw BadRequest::createWithBody(
            'allocationsOnlyIfPaid',
            Body::create()->withMessageTranslation('allocationsOnlyIfPaid', PaymentEntry::ENTITY_TYPE)
        );
    }
}
