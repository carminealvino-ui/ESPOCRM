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

namespace Espo\Modules\Sales\Tools\InventoryNumber\History;

use Espo\Core\Field\Date;
use Espo\Core\Field\Link;
use Espo\Core\Field\LinkParent;
use stdClass;

class Item
{
    public function __construct(
        private LinkParent $item,
        private LinkParent $order,
        private Date $date,
        private ?Link $warehouse,
        private ?float $quantity = null,
    ) {}

    /** @noinspection PhpUnused */
    public function getItem(): LinkParent
    {
        return $this->item;
    }

    public function getOrder(): LinkParent
    {
        return $this->order;
    }

    public function getQuantity(): ?float
    {
        return $this->quantity;
    }

    public function getWarehouse(): ?Link
    {
        return $this->warehouse;
    }

    public function getDate(): Date
    {
        return $this->date;
    }

    public function toValueMap(): stdClass
    {
        return (object) [
            'itemId' => $this->item->getId(),
            'itemName' => $this->item->getName(),
            'itemType' => $this->item->getEntityType(),
            'orderId' => $this->order->getId(),
            'orderName' => $this->order->getName(),
            'orderType' => $this->order->getEntityType(),
            'quantity' => $this->quantity,
            'date' => $this->date->getString(),
            'warehouseId' => $this->warehouse?->getId(),
            'warehouseName' => $this->warehouse?->getName(),
        ];
    }
}
