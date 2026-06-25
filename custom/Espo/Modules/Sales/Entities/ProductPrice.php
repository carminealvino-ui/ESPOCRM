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
use Espo\Core\ORM\Entity;

use RuntimeException;

/** @noinspection PhpUnused */
class ProductPrice extends Entity
{
    public const ENTITY_TYPE = 'ProductPrice';

    public const STATUS_ACTIVE = 'Active';
    public const STATUS_INACTIVE = 'Inactive';

    public const FIELD_STATUS = 'status';
    public const FIELD_INTERVAL = 'interval';
    public const FIELD_MIN_QUANTITY = 'minQuantity';
    public const FIELD_PRICE_BOOK = 'priceBook';

    public const ATTR_PRICE_CURRENCY = 'priceCurrency';

    /** @noinspection PhpUnused */
    public function _hasName(): bool
    {
        return $this->hasInContainer('productName');
    }

    /** @noinspection PhpUnused */
    public function _getName(): ?string
    {
        return $this->getFromContainer('productName');
    }

    public function getProduct(): Product
    {
        /** @var Product */
        return $this->relations->getOne('product');
    }

    public function getPrice(): Currency
    {
        /** @var ?Currency $value */
        $value = $this->getValueObject('price');

        if (!$value) {
            throw new RuntimeException("No price value in ProductPrice '$this->id'.");
        }

        return $value;
    }

    public function setPrice(Currency $price): self
    {
        $this->setValueObject('price', $price);

        return $this;
    }

    public function getPriceBook(): Link
    {
        /** @var ?Link $value */
        $value = $this->getValueObject(self::FIELD_PRICE_BOOK);

        if (!$value) {
            throw new RuntimeException("No price book in ProductPrice '$this->id'.");
        }

        return $value;
    }

    public function getInterval(): ?string
    {
        return $this->get(self::FIELD_INTERVAL);
    }

    public function setInterval(?string $interval): self
    {
        return $this->set(self::FIELD_INTERVAL, $interval);
    }

    public function setProduct(Product $product): self
    {
        return $this->setRelatedLinkOrEntity('product', $product);
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
