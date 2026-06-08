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

namespace Espo\Modules\Sales\Classes\FieldValidators\Quote;

use Espo\Core\FieldValidation\Exceptions\ValidationError;
use Espo\Core\FieldValidation\FieldValidationManager;
use Espo\Core\FieldValidation\Validator;
use Espo\Core\FieldValidation\Validator\Data;
use Espo\Core\FieldValidation\Validator\Failure;
use Espo\Core\Utils\Log;
use Espo\Core\Utils\Metadata;
use Espo\Modules\Sales\Entities\DeliveryOrder;
use Espo\Modules\Sales\Entities\DeliveryOrderItem;
use Espo\Modules\Sales\Entities\Product;
use Espo\Modules\Sales\Entities\ReceiptOrder;
use Espo\Modules\Sales\Entities\ReceiptOrderItem;
use Espo\Modules\Sales\Entities\TransferOrder;
use Espo\Modules\Sales\Entities\TransferOrderItem;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use stdClass;

/**
 * @implements Validator<OrderEntity>
 */
class ValidItems implements Validator
{
    public function __construct(
        private EntityManager $entityManager,
        private FieldValidationManager $validationManager,
        private Log $log,
        private Metadata $metadata,
    ) {}

    public function validate(Entity $entity, string $field, Data $data): ?Failure
    {
        $itemEntityType = $entity->getItemEntityType();
        $itemParentIdAttribute = lcfirst($entity->getEntityType()) . 'Id';

        /** @var stdClass[] $itemList */
        $itemList = $entity->get(OrderEntity::ATTR_ITEM_LIST) ?? [];

        $previousItems = !$entity->isNew() ?
            $this->entityManager
                ->getRDBRepository($itemEntityType)
                ->where([$itemParentIdAttribute => $entity->getId()])
                ->find() :
            null;

        /** @var array<string, Entity> $map */
        $map = [];

        if ($previousItems) {
            foreach ($previousItems as $item) {
                $map[$item->getId()] = $item;
            }
        }

        foreach ($itemList as $item) {
            $id = $item->id ?? null;

            $itemEntity = $id && array_key_exists($id, $map) ?
                $map[$id] :
                $this->entityManager->getNewEntity($itemEntityType);

            $itemEntity->set($item);

            try {
                $this->validationManager->process($itemEntity, $item);
            }
            catch (ValidationError $e) {
                $this->log->notice($e->getLogMessage());

                return Failure::create();
            }

            if (
                $entity instanceof ReceiptOrder ||
                $entity instanceof TransferOrder
            ) {
                /** @var ReceiptOrderItem|TransferOrderItem $itemEntity */

                if (!$this->checkItemQuantityReceived($itemEntity, $entity)) {
                    return Failure::create();
                }
            }

            if (
                $entity instanceof DeliveryOrder ||
                $entity instanceof TransferOrder
            ) {
                /** @var DeliveryOrderItem|TransferOrderItem $itemEntity */

                if (!$this->checkInventoryNumber($itemEntity, $entity)) {
                    return Failure::create();
                }
            }
        }

        return null;
    }

    private function checkItemQuantityReceived(Entity $item, ReceiptOrder|TransferOrder $order): bool
    {
        if (!in_array($order->getStatus(), $this->getDoneStatusList($order->getEntityType()))) {
            return true;
        }

        return $item->get('quantityReceived') !== null;
    }

    private function checkInventoryNumber(
        DeliveryOrderItem|TransferOrderItem $item,
        DeliveryOrder|TransferOrder $order
    ): bool {

        if (
            in_array(
                $order->getStatus(),
                $this->getSoftReservedCanceledStatusList($order->getEntityType())
            )
        ) {
            return true;
        }

        if (!$item->getProduct()) {
            return true;
        }

        $product = $this->entityManager
            ->getRDBRepositoryByClass(Product::class)
            ->getById($item->getProduct()->getId());

        if (!$product) {
            return true;
        }

        if (!$product->getInventoryNumberType()) {
            return true;
        }

        return $item->getInventoryNumber() !== null;
    }

    /**
     * @return string[]
     */
    private function getDoneStatusList(string $entityType): array
    {
        return $this->metadata->get("scopes.$entityType.doneStatusList") ?? [];
    }

    /**
     * @return string[]
     */
    private function getSoftReservedCanceledStatusList(string $entityType): array
    {
        return array_merge(
            $this->metadata->get("scopes.$entityType.softReserveStatusList") ?? [],
            $this->metadata->get("scopes.$entityType.canceledStatusList") ?? [],
        );
    }
}
