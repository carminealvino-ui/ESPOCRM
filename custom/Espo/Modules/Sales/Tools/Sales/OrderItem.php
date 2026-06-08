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

/** @noinspection PhpUnused */

namespace Espo\Modules\Sales\Tools\Sales;

use Espo\Core\Field\Link;
use stdClass;

/**
 * @phpstan-consistent-constructor
 */
class OrderItem
{
    /** @var array<string, mixed> */
    private array $data = [];

    private const FIELD_PRODUCT = 'product';

    public function __construct(
        private ?string $id = null,
        private ?string $name = null,
        private ?string $productId = null,
        private ?string $productName = null,
        private ?string $inventoryNumberId = null,
        private ?string $inventoryNumberName = null,
        private ?string $inventoryNumberType = null,
        private ?bool $isInventory = null,
        private ?float $quantity = null,
    ) {}

    public static function fromRaw(object $raw): static
    {
        $obj = new static(
            id: $raw->id ?? null,
            name: $raw->name ?? null,
            productId: $raw->productId ?? null,
            productName: $raw->productName ?? null,
            inventoryNumberId: $raw->inventoryNumberId ?? null,
            inventoryNumberName: $raw->inventoryNumberName ?? null,
            inventoryNumberType: $raw->inventoryNumberType ?? null,
            isInventory: $raw->isInventory ?? null,
            quantity: $raw->quantity ?? null,
        );

        /** @var static */
        return $obj->withData(get_object_vars($raw));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function withData(array $data): static
    {
        $obj = clone $this;

        foreach ($data as $key => $value) {
            $obj = $obj->with($key, $value);
        }

        return $obj;
    }

    public function with(string $name, mixed $value): static
    {
        $obj = clone $this;

        if (property_exists($this, $name) && $name !== 'data') {
            $obj->$name = $value;
        } else {
            $obj->data[$name] = $value;
        }

        return $obj;
    }

    public function withName(?string $name): static
    {
        $obj = clone $this;
        $obj->name = $name;

        return $obj;
    }

    public function withQuantity(?float $quantity): static
    {
        $obj = clone $this;
        $obj->quantity = $quantity;

        return $obj;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getProductId(): ?string
    {
        return $this->productId;
    }

    public function getProductName(): ?string
    {
        return $this->productName;
    }

    public function getProductLink(): ?Link
    {
        if (!$this->productId) {
            return null;
        }

        return Link::create($this->productId, $this->productName);
    }

    public function getInventoryNumberId(): ?string
    {
        return $this->inventoryNumberId;
    }

    public function getInventoryNumberName(): ?string
    {
        return $this->inventoryNumberName;
    }

    public function getInventoryNumberType(): ?string
    {
        return $this->inventoryNumberType;
    }

    public function isInventory(): ?bool
    {
        return $this->isInventory;
    }

    public function getQuantity(): ?float
    {
        return $this->quantity;
    }

    public function get(string $name): mixed
    {
        if (property_exists($this, $name) && $name !== 'data') {
            return $this->$name;
        }

        return $this->data[$name] ?? null;
    }

    public function toRaw(): stdClass
    {
        $raw = (object) [
            'id' => $this->id,
            'name' => $this->name,
            'productId' => $this->productId,
            'productName' => $this->productName,
            'inventoryNumberId' => $this->inventoryNumberId,
            'inventoryNumberName' => $this->inventoryNumberName,
            'inventoryNumberType' => $this->inventoryNumberType,
            'isInventory' => $this->isInventory,
            'quantity' => $this->quantity,
        ];

        foreach ($this->data as $key => $value) {
            $raw->$key = $value;
        }

        return $raw;
    }

    public function clone(): static
    {
        return static::fromRaw($this->toRaw());
    }

    public static function fromOrderItem(OrderItem $item): static
    {
        return static::fromRaw($item->toRaw());
    }

    public function withProduct(?Link $product): static
    {
        return $this
            ->with(self::FIELD_PRODUCT . 'Id', $product?->getId())
            ->with(self::FIELD_PRODUCT . 'Name', $product?->getName());
    }
}
