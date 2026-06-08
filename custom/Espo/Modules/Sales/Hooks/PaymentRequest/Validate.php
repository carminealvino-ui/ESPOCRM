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

namespace Espo\Modules\Sales\Hooks\PaymentRequest;

use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Hook\Hook\BeforeRemove;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Modules\Sales\Entities\PaymentRequest;
use Espo\Modules\Sales\Tools\PaymentRequest\ValidationHelper;
use Espo\Modules\Sales\Tools\Sales\RecordValidator;
use Espo\Modules\Sales\Tools\Sales\SaveOption;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\RemoveOptions;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * @implements BeforeSave<PaymentRequest>
 * @implements BeforeRemove<PaymentRequest>
 */
class Validate implements BeforeSave, BeforeRemove
{
    public static int $order = 12;

    public function __construct(
        private RecordValidator $recordValidator,
        private ValidationHelper $validationHelper,
    ) {}

    /**
     * @inheritDoc
     * @throws Forbidden
     * @throws Conflict
     */
    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(SaveOption::VALIDATE_ALL) || $options->get(SaveOption::VALIDATE_LOCKED)) {
            $this->recordValidator->validateLocked($entity);
        }

        if ($options->get(SaveOption::VALIDATE_ALL)) {
            $this->validationHelper->validate($entity);
        }
    }

    /**
     * @inheritDoc
     * @throws Conflict
     */
    public function beforeRemove(Entity $entity, RemoveOptions $options): void
    {
        if ($entity->isLocked()) {
            throw new Conflict("Cannot remove locked record.");
        }
    }
}
