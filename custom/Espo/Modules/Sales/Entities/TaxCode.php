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
use Espo\Modules\Sales\Tools\Tax\TaxCodeBase;
use Espo\Modules\Sales\Tools\Tax\TaxCodeType;
use Espo\Modules\Sales\Tools\Tax\TaxRoundingLevel;
use Espo\ORM\EntityCollection;
use stdClass;
use UnexpectedValueException;

class TaxCode extends Entity
{
    public const ENTITY_TYPE = 'TaxCode';

    public const STATUS_ACTIVE = 'Active';
    public const STATUS_INACTIVE = 'Inactive';

    public const FIELD_STATUS = 'status';
    public const FIELD_TYPE = 'type';
    public const FIELD_BASE = 'base';
    public const FIELD_ROUNDING_LEVEL = 'roundingLevel';
    public const FIELD_ITEMS = 'items';
    public const FIELD_IS_SELECTABLE = 'isSelectable';
    public const FIELD_IS_FOR_SALES = 'isForSales';
    public const FIELD_IS_FOR_PURCHASES = 'isForPurchases';
    public const FILED_CODE = 'code';
    public const FIELD_ORDER = 'order';
    public const FIELD_LABEL = 'label';
    public const FIELD_NAME = 'name';

    private const FIELD_ROUNDING_FACTOR = 'roundingFactor';

    private const FIELD_RATE = 'rate';
    private const FIELD_AMOUNT = 'amount';
    private const FIELD_COUNTRY = 'country';

    public const ATTR_ORDER = 'order';

    public const LINK_PARENT_ITEMS = 'parentItems';

    public const COLUMN_ORDER = 'order';

    /**
     * @param int $order
     */
    public function setOrder(int $order): self
    {
        return $this->set(self::FIELD_ORDER, $order);
    }

    /**
     * @return ?string
     */
    public function getName(): ?string
    {
        return $this->get(self::FIELD_NAME);
    }

    /**
     * @return ?string
     */
    public function getCode(): ?string
    {
        return $this->get(self::FILED_CODE);
    }

    /**
     * @return ?string
     */
    public function getLabel(): ?string
    {
        return $this->get(self::FIELD_LABEL);
    }

    public function setLabel(?string $label): self
    {
        return $this->set(self::FIELD_LABEL, $label);
    }

    /**
     * @return ?numeric-string
     */
    public function getRate(): ?string
    {
        return $this->get(self::FIELD_RATE);
    }

    /**
     * @return ?numeric-string
     */
    public function getAmount(): ?string
    {
        return $this->get(self::FIELD_AMOUNT);
    }

    public function setType(TaxCodeType $type): self
    {
        return $this->set(self::FIELD_TYPE, $type->value);
    }

    public function getType(): TaxCodeType
    {
        $raw = $this->get(self::FIELD_TYPE);

        if (!$raw) {
            throw new UnexpectedValueException("No type in tax code.");
        }

        return TaxCodeType::from($raw);
    }

    public function getRoundingLevel(): TaxRoundingLevel
    {
        $raw = $this->get(self::FIELD_ROUNDING_LEVEL);

        if (!$raw) {
            throw new UnexpectedValueException("No rounding level.");
        }

        return TaxRoundingLevel::from($raw);
    }

    public function setRoundingLevel(TaxRoundingLevel $roundingLevel): self
    {
        return $this->set(self::FIELD_ROUNDING_LEVEL, $roundingLevel->value);
    }

    /**
     * @return ?numeric-string
     */
    public function getRoundingFactor(): ?string
    {
        return $this->get(self::FIELD_ROUNDING_FACTOR);
    }

    /**
     * @param ?numeric-string $factor
     */
    public function setRoundingFactor(?string $factor): self
    {
        return $this->set(self::FIELD_ROUNDING_FACTOR, $factor);
    }

    public function isActive(): bool
    {
        return $this->get(self::FIELD_STATUS) === self::STATUS_ACTIVE;
    }

    public function isSelectable(): bool
    {
        return (bool) $this->get(self::FIELD_IS_SELECTABLE);
    }

    public function isForSales(): bool
    {
        return (bool) $this->get(self::FIELD_IS_FOR_SALES);
    }

    public function isForPurchases(): bool
    {
        return (bool) $this->get(self::FIELD_IS_FOR_PURCHASES);
    }

    public function getBase(): TaxCodeBase
    {
        $raw = $this->get(self::FIELD_BASE);

        if (!$raw) {
            throw new UnexpectedValueException("No TaxCode base.");
        }

        return TaxCodeBase::from($raw);
    }

    public function setBase(TaxCodeBase $base): self
    {
        return $this->set(self::FIELD_BASE, $base->value);
    }

    public function applyToProportionalShipping(): bool
    {
        return (bool) $this->get('applyToProportionalShipping');
    }

    public function setApplyToProportionalShipping(bool $apply): self
    {
        return $this->set('applyToProportionalShipping', $apply);
    }

    public function isIncludedInPrice(): bool
    {
        return (bool) $this->get('includedInPrice');
    }

    public function setIncludedInPrice(bool $includedInPrice): self
    {
        return $this->set('includedInPrice', $includedInPrice);
    }

    public function getOrder(): int
    {
        return (int) $this->get(self::ATTR_ORDER);
    }

    /**
     * @param ?numeric-string $amount
     */
    public function setAmount(?string $amount): self
    {
        return $this->set(self::FIELD_AMOUNT, $amount);
    }

    /**
     * @param ?numeric-string $rate
     */
    public function setRate(?string $rate): self
    {
        return $this->set(self::FIELD_RATE, $rate);
    }

    public function getCountry(): ?string
    {
        return $this->get(self::FIELD_COUNTRY);
    }

    public function setCountry(?string $country): self
    {
        return $this->set(self::FIELD_COUNTRY, $country);
    }

    /**
     * @param Link[] $items
     */
    public function setItems(array $items): self
    {
        return $this->set(self::FIELD_ITEMS, self::serializeItems($items));
    }

    public function isItemsChanged(): bool
    {
        if (!$this->hasFetched(self::FIELD_ITEMS)) {
            $this->getFetchedItems();
        }

        return $this->isAttributeChanged(self::FIELD_ITEMS);
    }

    /**
     * @return Link[]
     */
    public function getFetchedItems(): array
    {
        if ($this->isNew()) {
            return [];
        }

        if (!$this->hasFetched(self::FIELD_ITEMS)) {
            $serialized = self::serializeItems($this->loadItems());

            $this->setFetched(self::FIELD_ITEMS, $serialized);
        }

        $serialized = $this->getFetched(self::FIELD_ITEMS) ?? [];

        return self::unserializeItems($serialized);
    }

    /**
     * @return Link[]
     */
    public function getItems()
    {
        if (!$this->has(self::FIELD_ITEMS) && !$this->isNew()) {
            $items = $this->loadItems();

            $serialized = self::serializeItems($items);

            /** @phpstan-ignore-next-line function.alreadyNarrowedType */
            if (method_exists($this, 'setInContainerNotWritten')) {
                $this->setInContainerNotWritten(self::FIELD_ITEMS, $serialized);
            } else {
                $this->setInContainer(self::FIELD_ITEMS, $serialized);
            }

            $this->setFetched(self::FIELD_ITEMS, $serialized);

            return $items;
        }

        /** @var stdClass[] $serialized */
        $serialized = $this->has(self::FIELD_ITEMS) ?
            ($this->get(self::FIELD_ITEMS) ?? []) :
            [];

        return self::unserializeItems($serialized);
    }

    /**
     * @return Link[]
     */
    private function loadItems(): array
    {
        $collection = $this->getItemCollection();

        return array_map(
            function (TaxCode $taxCode) {
                return Link::create($taxCode->getId(), $taxCode->getName());
            },
            iterator_to_array($collection)
        );
    }

    /**
     * @param Link[] $items
     * @return (object{id: string, name?: string} & stdClass)[]
     */
    private static function serializeItems(array $items): array
    {
        return array_map(
            function (Link $it) {
                return (object) [
                    'id' => $it->getId(),
                    'name' => $it->getName(),
                ];
            },
            $items
        );
    }

    /**
     * @param stdClass[] $serialized
     * @return Link[]
     */
    private static function unserializeItems(array $serialized): array
    {
        return array_map(
            function (stdClass $it) {
                $id = $it->id ?? throw new UnexpectedValueException("No ID in item.");
                $name = $it->name ?? null;

                return Link::create($id, $name);
            },
            $serialized
        );
    }

    /**
     * Should be called only on a stored entity. If items are set to an un-saved entity,
     * it won't return those new items.
     *
     * @return EntityCollection<TaxCode>
     */
    public function getItemCollection(): EntityCollection
    {
        /** @var EntityCollection<TaxCode> */
        return $this->relations->getMany(self::FIELD_ITEMS);
    }
}
