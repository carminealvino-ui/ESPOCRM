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

namespace Espo\Modules\Sales\Hooks\SubscriptionTemplate;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Modules\Sales\Entities\SubscriptionTemplate;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * @implements BeforeSave<SubscriptionTemplate>
 */
class SetValues implements BeforeSave
{
    public static int $order = 11;

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        $this->setPrimaryProduct($entity);

        if (!$entity->hasTerm()) {
            $entity->setTermUnit(null);
            $entity->setTermLength(null);
        }

        if (!$entity->hasTrial()) {
            $entity->setTrialPeriodDays(null);
        }

        $this->setHasQuantity($entity);
    }

    private function setPrimaryProduct(SubscriptionTemplate $entity): void
    {
        if (!$entity->isAttributeChanged(OrderEntity::ATTR_ITEM_LIST)) {
            return;
        }

        $firstItem = $entity->getItems()[0] ?? null;

        if (!$firstItem) {
            $entity->setPrimaryProduct(null);

            return;
        }

        $entity->setPrimaryProduct($firstItem->getProductLink());
    }

    private function setHasQuantity(SubscriptionTemplate $entity): void
    {
        if (!$entity->has(OrderEntity::ATTR_ITEM_LIST)) {
            return;
        }

        $hasQuantity = false;

        foreach ($entity->getItems() as $item) {
            if (!$item->isFixedQuantity()) {
                $hasQuantity = true;

                break;
            }
        }

        $entity->set(SubscriptionTemplate::FIELD_HAS_QUANTITY, $hasQuantity);
    }
}
