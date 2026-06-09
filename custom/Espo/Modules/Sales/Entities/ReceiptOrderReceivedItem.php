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

use Espo\Core\Field\Link;
use Espo\Core\ORM\Entity;
use stdClass;

class ReceiptOrderReceivedItem extends Entity
{
    public const ENTITY_TYPE = 'ReceiptOrderReceivedItem';

    public function getQuantity(): float
    {
        return $this->get('quantity');
    }

    public function getInventoryNumber(): Link
    {
        /** @var Link */
        return $this->getValueObject('inventoryNumber');
    }

    public function getProduct(): Link
    {
        /** @var Link */
        return $this->getValueObject('product');
    }

    public function getOrder(): int
    {
        return $this->get('order') ?? 0;
    }

    public function getReceiptOrderId(): ?string
    {
        return $this->get('receiptOrderId');
    }

    public function getRawValues(): stdClass
    {
        return (object) [
            'id' => $this->getId(),
            'productId' => $this->getProduct()->getId(),
            'productName' => $this->getProduct()->getName(),
            'inventoryNumberId' => $this->getInventoryNumber()->getId(),
            'inventoryNumberName' => $this->getInventoryNumber()->getName(),
            'inventoryNumberType' => $this->get('inventoryNumberType'),
            'quantity' => $this->getQuantity(),
        ];
    }
}
