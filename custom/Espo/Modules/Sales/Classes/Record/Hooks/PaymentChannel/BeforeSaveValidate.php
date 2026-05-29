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

namespace Espo\Modules\Sales\Classes\Record\Hooks\PaymentChannel;

use Espo\Core\FieldValidation\Exceptions\ValidationError;
use Espo\Core\FieldValidation\FieldValidationManager;
use Espo\Core\Record\Hook\SaveHook;
use Espo\Modules\Sales\Entities\PaymentChannel;
use Espo\Modules\Sales\Tools\PaymentChannel\RecordProvider;
use Espo\ORM\Entity;

/**
 * @implements SaveHook<PaymentChannel>
 */
class BeforeSaveValidate implements SaveHook
{
    public function __construct(
        private RecordProvider $recordProvider,
        private FieldValidationManager $fieldValidationManager,
    ) {}

    /**
     * @inheritDoc
     * @throws ValidationError
     */
    public function process(Entity $entity): void
    {
        $record = $this->recordProvider->get($entity);

        $data = $entity->getData();

        if ($data) {
            $record->setMultiple($data);
        }

        if ($entity->isNew() || $data) {
            $this->fieldValidationManager->process($record, $data);
        }
    }
}
