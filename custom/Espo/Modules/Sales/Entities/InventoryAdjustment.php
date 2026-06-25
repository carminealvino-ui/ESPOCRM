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

namespace Espo\Modules\Sales\Entities;

use Espo\Core\Field\Date;
use Espo\Core\Field\Link;
use Espo\Modules\Crm\Entities\Account;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\Modules\Sales\Tools\Sales\OrderItem;

/**
 * @extends OrderEntity<OrderItem>
 */
class InventoryAdjustment extends OrderEntity
{
    public const ENTITY_TYPE = 'InventoryAdjustment';

    public const STATUS_COMPLETED = 'Completed';
    public const STATUS_STARTED = 'Started';
    public const STATUS_CANCELED = 'Canceled';

    public function getAccount(): ?Link
    {
        return null;
    }

    public function setAccount(?Account $account): static
    {
        return $this;
    }

    public function getWarehouse(): ?Link
    {
        /** @var ?Link */
        return $this->getValueObject('warehouse');
    }

    public function getDate(): ?Date
    {
        /** @var ?Date */
        return $this->getValueObject('date');
    }

    public function setWarehouseId(?string $warehouseId): static
    {
        $this->set('warehouseId', $warehouseId);

        return $this;
    }
}
