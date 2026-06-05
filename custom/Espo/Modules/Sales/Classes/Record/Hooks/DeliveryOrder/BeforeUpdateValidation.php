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

namespace Espo\Modules\Sales\Classes\Record\Hooks\DeliveryOrder;

use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Record\Hook\UpdateHook;
use Espo\Core\Record\UpdateParams;
use Espo\Modules\Sales\Entities\DeliveryOrder;
use Espo\Modules\Sales\Tools\DeliveryOrder\AvailabilityCheck;
use Espo\Modules\Sales\Tools\DeliveryOrder\ValidationHelper;
use Espo\ORM\Entity;

/**
 * @implements UpdateHook<DeliveryOrder>
 */
class BeforeUpdateValidation implements UpdateHook
{
    public function __construct(
        private ValidationHelper $validationHelper,
        private AvailabilityCheck $availabilityCheck
    ) {}

    /**
     * @param DeliveryOrder $entity
     * @throws Conflict
     * @noinspection PhpDocSignatureInspection
     */
    public function process(Entity $entity, UpdateParams $params): void
    {
        $this->processInAdjustmentCheck($entity);
        $this->processAvailabilityCheck($entity);
    }

    /**
     * @throws Conflict
     */
    private function processAvailabilityCheck(DeliveryOrder $entity): void
    {
        if (
            !$this->validationHelper->toValidateInventory($entity) ||
            $this->availabilityCheck->check($entity)
        ) {
            return;
        }

        $idPart = $entity->hasId() ? $entity->getId() : '(new)';

        $label = $entity->isAttributeChanged('status') ?
            'notAvailableInventoryStatusChanged' :
            'notAvailableInventory';

        $entityType = $entity->getEntityType();

        throw Conflict::createWithBody(
            "Not available inventory for $entityType $idPart.",
            Body::create()
                ->withMessageTranslation($label, DeliveryOrder::ENTITY_TYPE)
                ->encode()
        );
    }

    /**
     * @throws Conflict
     */
    private function processInAdjustmentCheck(DeliveryOrder $entity): void
    {
        if (
            !$this->validationHelper->toProcessInventorySave($entity) ||
            $this->availabilityCheck->checkNotBeingAdjusted($entity)
        ) {
            return;
        }

        $idPart = $entity->hasId() ? $entity->getId() : '(new)';

        throw Conflict::createWithBody(
            "Inventory for DeliveryOrder $idPart is in adjustment.",
            Body::create()
                ->withMessageTranslation('inventoryIsInAdjustment', DeliveryOrder::ENTITY_TYPE)
                ->encode()
        );
    }
}
