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

namespace Espo\Modules\Sales\Hooks\ReceiptOrder;

use Espo\Core\Exceptions\Conflict;
use Espo\Modules\Sales\Entities\ReceiptOrder;
use Espo\Modules\Sales\Entities\InventoryTransaction;
use Espo\Modules\Sales\Tools\ReceiptOrder\AvailabilityCheck;
use Espo\Modules\Sales\Tools\ReceiptOrder\ValidationHelper;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\Modules\Sales\Tools\Sales\RecordValidator;
use Espo\Modules\Sales\Tools\Sales\SaveOption;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class Validate
{
    public static int $order = 12;

    public function __construct(
        private SerialNumberCheck $check,
        private ValidationHelper $validationHelper,
        private EntityManager $entityManager,
        private AvailabilityCheck $availabilityCheck,
        private RecordValidator $recordValidator,
    ) {}

    /**
     * @param ReceiptOrder $entity
     * @param array<string, mixed> $options
     * @throws Conflict
     */
    public function beforeSave(Entity $entity, array $options): void
    {
        if (!empty($options[SaveOption::VALIDATE_AVAILABLE_SERIAL_NUMBERS])) {
            $this->validateSerialNumbers($entity);
            $this->validateNonInAdjustment($entity);
        }

        if (!empty($options[SaveOption::VALIDATE_LOCKED])) {
            $this->recordValidator->validateLocked($entity);
        }
    }

    /**
     * @param OrderEntity $entity
     * @param array<string, mixed> $options
     * @throws Conflict
     * @noinspection PhpUnusedParameterInspection
     */
    public function beforeRemove(Entity $entity, array $options): void
    {
        if ($entity->isLocked()) {
            throw new Conflict("Cannot remove locked record.");
        }
    }

    /**
     * @throws Conflict
     */
    private function validateSerialNumbers(ReceiptOrder $entity): void
    {
        if (!$this->validationHelper->toValidateSerialNumbers($entity)) {
            return;
        }

        $this->entityManager
            ->getRDBRepository(InventoryTransaction::ENTITY_TYPE)
            ->sth()
            ->select('id')
            ->forUpdate()
            ->where(['productId' => $entity->getInventoryProductIds()])
            ->find();

        if (!$this->check->check($entity)) {
            $idString = $entity->hasId() ? $entity->getId() : '(new)';

            throw new Conflict("Cannot receive a serial number that is already in stock for ReceiptOrder $idString.");
        }
    }

    /**
     * @throws Conflict
     */
    private function validateNonInAdjustment(ReceiptOrder $entity): void
    {
        if (
            !$this->validationHelper->toProcessInventorySave($entity) ||
            $this->availabilityCheck->checkNotBeingAdjusted($entity)
        ) {
            return;
        }

        $idString = $entity->hasId() ? $entity->getId() : '(new)';

        throw new Conflict("Inventory is in adjustment for ReceiptOrder $idString.");
    }
}
