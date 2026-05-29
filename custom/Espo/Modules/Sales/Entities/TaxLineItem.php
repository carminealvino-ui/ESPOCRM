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
use Espo\Core\Field\LinkParent;
use Espo\Core\ORM\Entity;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use UnexpectedValueException;

class TaxLineItem extends Entity
{
    public const ENTITY_TYPE = 'TaxLineItem';

    public const COMPONENT_ITEM = 'Item';
    public const COMPONENT_SHIPPING = 'Shipping';

    public const FIELD_SOURCE = 'source';
    public const FIELD_PRODUCT = 'product';
    public const FIELD_ITEM = 'item';
    public const FIELD_ORDER = 'order';
    public const FIELD_AMOUNT_LOCAL = 'amountLocal';
    public const FIELD_BASE_AMOUNT_LOCAL = 'baseAmountLocal';

    public const ATTR_AMOUNT_LOCAL_CURRENCY = 'amountLocalCurrency';
    public const ATTR_TAX_CODE_ID = 'taxCodeId';

    /**
     * @return ?numeric-string
     */
    public function getRate(): ?string
    {
        return $this->get('rate');
    }

    public function getTaxCode(): TaxCode
    {
        $taxCode =  $this->relations->getOne('taxCode');

        if (!$taxCode instanceof TaxCode) {
            throw new UnexpectedValueException("No tax code.");
        }

        return $taxCode;
    }

    public function getAmount(): Currency
    {
        $value = $this->getValueObject('amount');

        if (!$value instanceof Currency) {
            throw new UnexpectedValueException("No amount.");
        }

        return $value;
    }

    public function getBaseAmount(): Currency
    {
        /** @var ?numeric-string $baseAmount */
        $baseAmount = $this->get('baseAmount');

        if ($baseAmount === null) {
            throw new UnexpectedValueException("No base amount.");
        }

        return Currency::create($baseAmount, $this->getAmount()->getCode());
    }

    public function getAmountPrecise(): Currency
    {
        /** @var ?numeric-string $amountPrecise */
        $amountPrecise = $this->get('amountPrecise');

        if ($amountPrecise === null) {
            throw new UnexpectedValueException("No amount precise.");
        }

        return Currency::create($amountPrecise, $this->getAmount()->getCode());
    }

    public function getAmountLocalPrecise(): ?Currency
    {
        /** @var ?numeric-string $amountLocalPrecise */
        $amountLocalPrecise = $this->get('amountLocalPrecise');

        if ($amountLocalPrecise === null) {
            return null;
        }

        if (!$this->getAmountLocal()) {
            return null;
        }

        return Currency::create($amountLocalPrecise, $this->getAmountLocal()->getCode());
    }

    public function getAmountLocal(): ?Currency
    {
        $value = $this->getValueObject(self::FIELD_AMOUNT_LOCAL);

        if (!$value instanceof Currency) {
            return null;
        }

        return $value;
    }

    public function getBaseAmountLocal(): ?Currency
    {
        /** @var ?numeric-string $baseAmount */
        $baseAmount = $this->get(self::FIELD_BASE_AMOUNT_LOCAL);

        if ($baseAmount === null) {
            return null;
        }

        if (!$this->getAmountLocal()) {
            return null;
        }

        return Currency::create($baseAmount, $this->getAmountLocal()->getCode());
    }

    public function getBaseAmountLocalPrecise(): ?Currency
    {
        /** @var ?numeric-string $amount */
        $amount = $this->get('baseAmountLocalPrecise');

        if ($amount === null) {
            return null;
        }

        if (!$this->getAmountLocal()) {
            return null;
        }

        return Currency::create($amount, $this->getAmountLocal()->getCode());
    }

    public function setOrder(int $order): self
    {
        return $this->set(self::FIELD_ORDER, $order);
    }

    public function getComponent(): ?string
    {
        return $this->get('component');
    }

    public function setComponent(string $component): self
    {
        return $this->set('component', $component);
    }

    public function setProduct(?Product $product): self
    {
        return $this->setRelatedLinkOrEntity(self::FIELD_PRODUCT, $product);
    }

    public function setTaxCode(TaxCode $taxCode): self
    {
        return $this->setRelatedLinkOrEntity('taxCode', $taxCode);
    }

    public function setAmount(Currency $amount): self
    {
        return $this->setValueObject('amount', $amount);
    }

    /**
     * @param numeric-string $amount
     */
    public function setBaseAmount(string $amount): self
    {
        return $this->set('baseAmount', $amount);
    }

    public function setAmountLocal(?Currency $amountLocal): self
    {
        return $this->setValueObject('amountLocal', $amountLocal);
    }

    /**
     * @param numeric-string $amountPrecise
     */
    public function setAmountPrecise(string $amountPrecise): self
    {
        return $this->set('amountPrecise', $amountPrecise);
    }

    /**
     * @param ?numeric-string $amountLocalPrecise
     */
    public function setAmountLocalPrecise(?string $amountLocalPrecise): self
    {
        return $this->set('amountLocalPrecise', $amountLocalPrecise);
    }

    /**
     * @param ?numeric-string $baseAmountLocalPrecise
     */
    public function setBaseAmountLocalPrecise(?string $baseAmountLocalPrecise): self
    {
        return $this->set('baseAmountLocalPrecise', $baseAmountLocalPrecise);
    }

    /**
     * @param ?numeric-string $baseAmountLocal
     */
    public function setBaseAmountLocal(?string $baseAmountLocal): self
    {
        return $this->set('baseAmountLocal', $baseAmountLocal);
    }

    /**
     * @param ?numeric-string $rate
     */
    public function setRate(?string $rate): self
    {
        return $this->set('rate', $rate);
    }

    public function setItem(?LinkParent $item): self
    {
        return $this->setValueObject(self::FIELD_ITEM, $item);
    }

    public function setSource(OrderEntity $source): self
    {
        return $this->setRelatedLinkOrEntity(self::FIELD_SOURCE, $source);
    }

    public function getSource(): OrderEntity
    {
        $source = $this->relations->getOne('source');

        if (!$source instanceof OrderEntity) {
            throw new UnexpectedValueException("No source.");
        }

        return $source;
    }

    public function getItemLink(): ?LinkParent
    {
        /** @var ?LinkParent */
        return $this->getValueObject(self::FIELD_ITEM);
    }

    public function getProductLink(): ?Link
    {
        /** @var ?Link */
        return $this->getValueObject(self::FIELD_PRODUCT);
    }
}
