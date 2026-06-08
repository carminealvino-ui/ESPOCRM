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

use Espo\Core\Field\Currency;
use Espo\Core\Field\Link;
use Espo\Core\Field\LinkMultiple;
use Espo\Core\ORM\Entity;
use Espo\Modules\Sales\Tools\ProductAttribute\AttributeItem;
use Espo\Modules\Sales\Tools\ProductAttribute\OptionItem;

class Product extends Entity
{
    public const ENTITY_TYPE = 'Product';

    public const STATUS_AVAILABLE = 'Available';
    public const STATUS_UNAVAILABLE = 'Unavailable';

    public const TYPE_REGULAR = 'Regular';
    public const TYPE_TEMPLATE = 'Template';
    public const TYPE_VARIANT = 'Variant';

    public const PRICING_TYPE_SAME_AS_LIST = 'Same as List';
    public const PRICING_TYPE_FIXED = 'Fixed';
    public const PRICING_TYPE_DISCOUNT_FROM_LIST = 'Discount from List';
    public const PRICING_TYPE_MARKUP_OVER_COST = 'Markup over Cost';
    public const PRICING_TYPE_PROFIT_MARGIN = 'Profit Margin';

    public const ITEM_TYPE_GOODS = 'Goods';
    public const ITEM_TYPE_SERVICE = 'Service';

    public const FIELD_STATUS = 'status';
    public const FIELD_IS_SUBSCRIBABLE = 'isSubscribable';
    public const FIELD_ITEM_TYPE = 'itemType';
    public const FIELD_COST_PRICE = 'costPrice';
    public const FIELD_UNIT_PRICE = 'unitPrice';
    public const FIELD_LIST_PRICE = 'listPrice';
    public const FIELD_PRICING_FACTOR = 'pricingFactor';
    public const FIELD_PRICING_TYPE = 'pricingType';
    public const FIELD_IS_SELLABLE = 'isSellable';
    public const FIELD_IS_PURCHASABLE = 'isPurchasable';

    public function getName(): ?string
    {
        return $this->get('name');
    }

    /**
     * @return self::ITEM_TYPE_*
     */
    public function getItemType(): string
    {
        return $this->get(self::FIELD_ITEM_TYPE);
    }

    /**
     * @param self::ITEM_TYPE_* $type
     */
    public function setItemType(string $type): self
    {
        return $this->set(self::FIELD_ITEM_TYPE, $type);
    }

    /**
     * @return self::TYPE_VARIANT|self::TYPE_TEMPLATE|self::TYPE_REGULAR
     */
    public function getType(): string
    {
        /** @var self::TYPE_* */
        return (string) $this->get('type');
    }

    /**
     * @param self::TYPE_VARIANT|self::TYPE_TEMPLATE|self::TYPE_REGULAR $type
     */
    public function setType(string $type): self
    {
        $this->set('type', $type);

        return $this;
    }

    /**
     * @return self::STATUS_AVAILABLE|self::STATUS_UNAVAILABLE
     */
    public function getStatus(): string
    {
        return $this->get('status');
    }

    /**
     * @param self::STATUS_AVAILABLE|self::STATUS_UNAVAILABLE $status
     */
    public function setStatus(string $status): self
    {
        $this->set('status', $status);

        return $this;
    }

    public function getPricingType(): ?string
    {
        return $this->get('pricingType');
    }

    public function getPricingFactor(): ?float
    {
        return $this->get('pricingFactor');
    }

    public function getTemplate(): ?Link
    {
        /** @var ?Link */
        return $this->getValueObject('template');
    }

    public function allowFractionalQuantity(): bool
    {
        return $this->get('allowFractionalQuantity');
    }

    public function getUnitPrice(): ?Currency
    {
        /** @var ?Currency */
        return $this->getValueObject('unitPrice');
    }

    public function setUnitPrice(?Currency $unitPrice): self
    {
        $this->setValueObject('unitPrice', $unitPrice);

        return $this;
    }

    public function getListPrice(): ?Currency
    {
        /** @var ?Currency */
        return $this->getValueObject('listPrice');
    }

    public function setListPrice(?Currency $listPrice): self
    {
        $this->setValueObject('listPrice', $listPrice);

        return $this;
    }

    public function getCostPrice(): ?Currency
    {
        /** @var ?Currency */
        return $this->getValueObject('costPrice');
    }

    public function setCostPrice(?Currency $costPrice): self
    {
        $this->setValueObject('costPrice', $costPrice);

        return $this;
    }

    public function isTaxFree(): bool
    {
        return $this->get('isTaxFree');
    }

    /**
     * @return InventoryNumber::TYPE_*|null
     */
    public function getInventoryNumberType(): ?string
    {
        return $this->get('inventoryNumberType');
    }

    public function setExpirationDays(?int $days): self
    {
        return $this->set('expirationDays', $days);
    }

    public function setPartNumber(?string $number): self
    {
        return $this->set('partNumber', $number);
    }

    public function getWeight(): ?float
    {
        return $this->get('weight');
    }

    public function setWeight(?float $weight): self
    {
        return $this->set('weight', $weight);
    }

    public function isSubscribable(): bool
    {
        return $this->get('isSubscribable');
    }

    public function setIsSubscribable(bool $isSubscribable): self
    {
        $this->set('isSubscribable', $isSubscribable);

        return $this;
    }

    public function isInventory(): bool
    {
        return $this->get('isInventory');
    }

    public function setIsInventory(bool $isInventory): self
    {
        $this->set('isInventory', $isInventory);

        return $this;
    }

    public function setAllowFractionalQuantity(bool $value): self
    {
        $this->set('allowFractionalQuantity', $value);

        return $this;
    }

    public function setInventoryNumberType(?string $value): self
    {
        $this->set('inventoryNumberType', $value);

        return $this;
    }

    public function setName(string $value): self
    {
        $this->set('name', $value);

        return $this;
    }

    public function loadAttributesField(): void
    {
        /** @var iterable<ProductAttribute> $attributes */
        $attributes = $this->entityManager
            ->getRDBRepositoryByClass(self::class)
            ->getRelation($this, 'attributes')
            ->select(['id', 'name'])
            ->order('order')
            ->find();

        /** @var iterable<ProductAttributeOption> $options */
        $options = $this->entityManager
            ->getRDBRepositoryByClass(self::class)
            ->getRelation($this, 'attributeOptions')
            ->select(['id', 'name', 'attributeId'])
            ->order('order')
            ->find();

        $dataList = [];

        foreach ($attributes as $attribute) {
            $optionItems = [];

            foreach ($options as $option) {
                if ($option->getProductAttribute()->getId() !== $attribute->getId()) {
                    continue;
                }

                $optionItems[] = (object) [
                    'id' => $option->getId(),
                    'name' => $option->getName(),
                ];
            }

            $dataList[] = (object) [
                'id' => $attribute->getId(),
                'name' => $attribute->getName(),
                'options' => $optionItems,
            ];
        }

        $this->set('attributes', $dataList);

        if (!$this->hasFetched('attributes')) {
            $this->setFetched('attributes', $dataList);
        }
    }

    /**
     * @return AttributeItem[]
     * @noinspection PhpUnused
     */
    public function getAttributeItems(): array
    {
        if (!$this->has('attributes') && $this->hasId()) {
            $this->loadAttributesField();
        }

        /** @var object{id: string, name: string, options: object{id: string, name: string}[]}[] $dataList */
        $dataList = $this->get('attributes') ?? [];

        $list = [];

        foreach ($dataList as $item) {
            $options = [];

            foreach ($item->options as $optionItem) {
                $options[] = new OptionItem($optionItem->id, $optionItem->name ?? $optionItem->id);
            }

            $list[] = new AttributeItem($item->id, $item->name, $options);
        }

        return $list;
    }

    /**
     * @param AttributeItem[] $attributes
     * @noinspection PhpUnused
     */
    public function setAttributeItems(array $attributes): self
    {
        $dataList = [];

        foreach ($attributes as $attribute) {
            $dataList[] = (object) [
                'id' => $attribute->getId(),
                'name' => $attribute->getName(),
                'options' => array_map(
                    fn ($item) => (object) [
                        'id' => $item->getId(),
                        'name' => $item->getName(),
                    ],
                    $attribute->getOptions()
                ),
            ];
        }
        $this->set('attributes', $dataList);

        return $this;
    }

    public function hasAttributeItems(): bool
    {
        return $this->has('attributes');
    }

    public function getExpirationDays(): ?int
    {
        return $this->get('expirationDays');
    }

    public function getCategory(): ?Link
    {
        /** @var ?Link */
        return $this->getValueObject('category');
    }

    public function getTaxClasses(): LinkMultiple
    {
        /** @var LinkMultiple */
        return $this->getValueObject('taxClasses');
    }

    public function setTeams(LinkMultiple $teams): self
    {
        $this->setValueObject('teams', $teams);

        return $this;
    }

    public function isSellable(): bool
    {
        return $this->get(self::FIELD_IS_SELLABLE);
    }

    public function isPurchasable(): bool
    {
        return $this->get(self::FIELD_IS_PURCHASABLE);
    }
}
