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

namespace Espo\Modules\Sales\Tools\ReceiptOrder;

use Espo\Core\Utils\Metadata;
use Espo\Modules\Sales\Entities\ReceiptOrder;
use Espo\Modules\Sales\Tools\Sales\ConfigDataProvider;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;

class ValidationHelper
{
    public function __construct(
        private Metadata $metadata,
        private ConfigDataProvider $configDataProvider
    ) {}

    public function toProcessInventorySave(ReceiptOrder $entity): bool
    {
        return
            $this->configDataProvider->isReceiptOrdersEnabled() &&
            $this->configDataProvider->isInventoryTransactionsEnabled() &&
            !$entity->isLocked() &&
            (
                $entity->isNew() ||
                $entity->isAttributeChanged('status') ||
                $entity->isAttributeChanged(OrderEntity::ATTR_ITEM_LIST)
            );
    }


    public function toValidateSerialNumbers(ReceiptOrder $order): bool
    {
        if ($order->getInventoryProductIds() === []) {
            return false;
        }

        return
            (
                $order->isNew() ||
                !in_array($order->getFetchedStatus(), $this->getDoneStatusList()) ||
                $order->isAttributeChanged(OrderEntity::ATTR_ITEM_LIST)
            ) &&
            in_array($order->getStatus(), $this->getDoneStatusList());
    }

    /**
     * @return string[]
     */
    private function getDoneStatusList(): array
    {
        return $this->metadata->get("scopes.ReceiptOrder.doneStatusList") ?? [];
    }
}
