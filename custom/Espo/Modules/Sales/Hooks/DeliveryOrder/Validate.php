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

namespace Espo\Modules\Sales\Hooks\DeliveryOrder;

use Espo\Core\Exceptions\Conflict;
use Espo\Modules\Sales\Entities\DeliveryOrder;
use Espo\Modules\Sales\Entities\InventoryTransaction;
use Espo\Modules\Sales\Tools\DeliveryOrder\AvailabilityCheck;
use Espo\Modules\Sales\Tools\DeliveryOrder\ValidationHelper;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\Modules\Sales\Tools\Sales\RecordValidator;
use Espo\Modules\Sales\Tools\Sales\SaveOption;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class Validate
{
    public static int $order = 12;

    public function __construct(
        private AvailabilityCheck $availabilityCheck,
        private ValidationHelper $validationHelper,
        private EntityManager $entityManager,
        private RecordValidator $recordValidator,
    ) {}

    /**
     * @param DeliveryOrder $entity
     * @param array<string, mixed> $options
     * @throws Conflict
     */
    public function beforeSave(Entity $entity, array $options): void
    {
        if (!empty($options[SaveOption::VALIDATE_INVENTORY])) {
            $this->validateInventory($entity);
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
    private function validateInventory(DeliveryOrder $entity): void
    {
        if (!$this->validationHelper->toValidateInventory($entity)) {
            return;
        }

        $this->entityManager
            ->getRDBRepository(InventoryTransaction::ENTITY_TYPE)
            ->sth()
            ->select('id')
            ->forUpdate()
            ->where(['productId' => $entity->getInventoryProductIds()])
            ->find();

        if (!$this->availabilityCheck->check($entity)) {
            $idString = $entity->hasId() ? $entity->getId() : '(new)';

            throw new Conflict("Not available inventory for DeliveryOrder $idString.");
        }
    }

    /**
     * @throws Conflict
     */
    private function validateNonInAdjustment(DeliveryOrder $entity): void
    {
        if (
            !$this->validationHelper->toProcessInventorySave($entity) ||
            $this->availabilityCheck->checkNotBeingAdjusted($entity)
        ) {
            return;
        }

        $idString = $entity->hasId() ? $entity->getId() : '(new)';

        throw new Conflict("Inventory is in adjustment for DeliveryOrder $idString.");
    }
}
