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

namespace Espo\Modules\Sales\Hooks\Subscription;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Modules\Sales\Entities\Subscription;
use Espo\Modules\Sales\Entities\SubscriptionItem;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\Modules\Sales\Tools\Subscription\IntervalUnit;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * @implements BeforeSave<Subscription>
 */
class SetValues implements BeforeSave
{
    public static int $order = 11;

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        $this->setAnchorDay($entity);
        $this->setPrimaryProduct($entity);
        $this->prepareForTemplate($entity);

        $this->fixItems($entity);
    }

    private function setAnchorDay(Subscription $entity): void
    {
        if (
            !$entity->isAttributeChanged(Subscription::FIELD_ANCHOR_DAY) &&
            !$entity->isAttributeChanged(Subscription::ATTR_BILLING_PLAN_ID)
        ) {
            return;
        }

        $plan = $entity->getBillingPlan();

        $interval = $plan->getIntervalUnit();

        if (
            $interval !== IntervalUnit::Month &&
            $interval !== IntervalUnit::Year
        ) {
            $entity->setAnchorDay(null);
        }
    }

    private function setPrimaryProduct(Subscription $entity): void
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

    private function prepareForTemplate(Subscription $entity): void
    {
        if (
            !$entity->isNew() ||
            !$entity->get(Subscription::ATTR_TEMPLATE_ID)
        ) {
            return;
        }

        $entity->setStatus(Subscription::STATUS_PAUSED);
    }

    private function fixItems(Subscription $entity): void
    {
        $items = $entity->getItems();

        foreach ($items as $i => $item) {
            $item = $item->with(SubscriptionItem::ATTR_SUBSCRIPTION_UPDATE_ID, null);

            $items[$i] = $item;
        }

        $entity->setItems($items);
    }
}
