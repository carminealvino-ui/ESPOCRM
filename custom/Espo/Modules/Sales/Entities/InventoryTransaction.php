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
use Espo\Core\Field\LinkParent;
use Espo\Core\ORM\Entity;
use RuntimeException;

class InventoryTransaction extends Entity
{
    public const ENTITY_TYPE = 'InventoryTransaction';

    public const TYPE_TRANSFER = 'Transfer';
    public const TYPE_RESERVE = 'Reserve';
    public const TYPE_SOFT_RESERVE = 'Soft Reserve';

    /** @noinspection PhpUnused */
    public function _hasName(): bool
    {
        return $this->hasInContainer('number');
    }

    /** @noinspection PhpUnused */
    public function _getName(): ?string
    {
        return $this->getFromContainer('number');
    }

    /**
     * @return self::TYPE_TRANSFER|self::TYPE_RESERVE|self::TYPE_SOFT_RESERVE
     */
    public function getType(): string
    {
        return $this->get('type');
    }

    public function getQuantity(): float
    {
        return $this->get('quantity');
    }

    public function getProduct(): Link
    {
        /** @var ?Link $value */
        $value = $this->getValueObject('product');

        if (!$value) {
            throw new RuntimeException("No product in transaction '$this->id'.");
        }

        return $value;
    }

    public function getWarehouse(): ?Link
    {
        /** @var ?Link */
        return $this->getValueObject('warehouse');
    }

    public function getInventoryNumber(): ?Link
    {
        /** @var ?Link */
        return $this->getValueObject('inventoryNumber');
    }

    public function setQuantity(float $quantity): self
    {
        $this->set('quantity', $quantity);

        return $this;
    }

    public function setProductId(string $productId): self
    {
        $this->set('productId', $productId);

        return $this;
    }

    public function setParent(LinkParent $parent): self
    {
        $this->setValueObject('parent', $parent);

        return $this;
    }

    /**
     * @param self::TYPE_TRANSFER|self::TYPE_RESERVE|self::TYPE_SOFT_RESERVE $type
     */
    public function setType(string $type): self
    {
        $this->set('type', $type);

        return $this;
    }

    public function setWarehouseId(?string $warehouseId): self
    {
        $this->set('warehouseId', $warehouseId);

        return $this;
    }

    public function setInventoryNumberId(?string $inventoryNumberId): self
    {
        $this->set('inventoryNumberId', $inventoryNumberId);

        return $this;
    }
}
