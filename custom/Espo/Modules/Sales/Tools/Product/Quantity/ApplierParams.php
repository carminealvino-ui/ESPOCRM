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

namespace Espo\Modules\Sales\Tools\Product\Quantity;

class ApplierParams
{
    public const TYPE_AVAILABLE = 0;
    public const TYPE_RESERVED = 1;
    public const TYPE_SOFT_RESERVED = 2;
    public const TYPE_ON_HAND = 3;

    /**
     * @param self::TYPE_* $type
     * @param ?string[] $warehouseFilterIds
     */
    public function __construct(
        private int $type = self::TYPE_AVAILABLE,
        private ?string $parentType = null,
        private ?string $parentId = null,
        private ?string $warehouseId = null,
        private bool $excludeSoftReserve = false,
        private bool $isNumber = false,
        private ?array $warehouseFilterIds = null,
    ) {}

    public function getParentType(): ?string
    {
        return $this->parentType;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function getWarehouseId(): ?string
    {
        return $this->warehouseId;
    }

    public function excludeSoftReserve(): bool
    {
        return $this->excludeSoftReserve;
    }

    /**
     * @return self::TYPE_*
     */
    public function getType(): int
    {
        return $this->type;
    }

    public function isNumber(): bool
    {
        return $this->isNumber;
    }

    /**
     * @return ?string[]
     */
    public function getWarehouseFilterIds(): ?array
    {
        return $this->warehouseFilterIds;
    }
}
