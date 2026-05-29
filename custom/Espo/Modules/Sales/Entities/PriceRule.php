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

use RuntimeException;

/** @noinspection PhpUnused */
class PriceRule extends Entity
{
    public const ENTITY_TYPE = 'PriceRule';

    public const STATUS_ACTIVE = 'Active';
    public const STATUS_INACTIVE = 'Inactive';

    public const TARGET_ALL = 'All';
    public const TARGET_PRODUCT_CATEGORY = 'Product Category';
    public const TARGET_CONDITIONAL = 'Conditional';

    public const ROUNDING_METHOD_HALF_UP = 'Half Up';
    public const ROUNDING_METHOD_UP = 'Up';
    public const ROUNDING_METHOD_DOWN = 'Down';

    public const BASED_ON_UNIT = 'Unit';
    public const BASED_ON_PRICE_BOOK = 'Price Book';
    public const BASED_ON_SUPPLIER = 'Supplier';
    public const BASED_ON_COST = 'Cost';

    private const FIELD_TARGET = 'target';
    private const FIELD_MIN_QUANTITY = 'minQuantity';
    public const FIELD_PRICE_BOOK = 'priceBook';

    /** @noinspection PhpUnused */
    public function _hasName(): bool
    {
        return $this->id !== null;
    }

    /** @noinspection PhpUnused */
    public function _getName(): ?string
    {
        return $this->id;
    }

    public function getDiscount(): float
    {
        return $this->get('discount') ?? 0.0;
    }

    public function getPriceBook(): Link
    {
        /** @var ?Link $value */
        $value = $this->getValueObject('priceBook');

        if (!$value) {
            throw new RuntimeException("No price book in PriceRule '$this->id'.");
        }

        return $value;
    }

    public function getBasedOn(): string
    {
        return $this->get('basedOn');
    }

    public function getTarget(): string
    {
        return $this->get('target');
    }

    public function getSupplier(): ?Link
    {
        /** @var ?Link */
        return $this->getValueObject('supplier');
    }

    public function getCondition(): ?Link
    {
        /** @var ?Link */
        return $this->getValueObject('condition');
    }

    public function getRoundingMethod(): string
    {
        return (string) $this->get('roundingMethod');
    }

    public function getRoundingFactor(): float
    {
        return (float) $this->get('roundingFactor');
    }

    public function getCurrency(): ?string
    {
        return $this->get('currency');
    }

    public function getSurcharge(): ?float
    {
        return $this->get('surcharge');
    }

    public function setDiscount (?float $discount): self
    {
        $this->set('discount', $discount);

        return $this;
    }

    public function setSurcharge(?float $surcharge): self
    {
        $this->set('surcharge', $surcharge);

        return $this;
    }

    public function setRoundingFactor(float $roundingFactor): self
    {
        $this->set('roundingFactor', $roundingFactor);

        return $this;
    }

    public function setRoundingMethod(string $roundingMethod): self
    {
        $this->set('roundingMethod', $roundingMethod);

        return $this;
    }

    public function setTarget(string $target): self
    {
        return $this->set(self::FIELD_TARGET, $target);
    }

    public function setMinQuantity(?float $minQuantity): self
    {
        return $this->set(self::FIELD_MIN_QUANTITY, $minQuantity);
    }

    public function setPriceBook(Link|PriceBook $priceBook): self
    {
        return $this->setRelatedLinkOrEntity(self::FIELD_PRICE_BOOK, $priceBook);
    }
}
