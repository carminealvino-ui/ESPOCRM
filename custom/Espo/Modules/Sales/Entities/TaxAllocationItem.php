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

class TaxAllocationItem extends Entity
{
    public const ENTITY_TYPE = 'TaxAllocationItem';

    public const FIELD_SOURCE = 'source';
    public const FIELD_ORDER = 'order';
    public const FIELD_AMOUNT_LOCAL = 'amountLocal';
    public const FIELD_BASE_AMOUNT_LOCAL = 'baseAmountLocal';
    public const FIELD_ALLOCATION = 'allocation';
    public const FIELD_PORTION = 'portion';
    public const FIELD_PAYMENT_ENTRY = 'paymentEntry';
    public const FIELD_PRODUCT = 'product';
    public const FIELD_PERCENTAGE = 'percentage';
    public const FIELD_ITEM = 'item';

    public const ATTR_AMOUNT_LOCAL_CURRENCY = 'amountLocalCurrency';
    public const ATTR_TAX_CODE_ID = 'taxCodeId';
    public const ATTR_SOURCE_TYPE = 'sourceType';
    public const ATTR_SOURCE_ID = 'sourceId';
    public const ATTR_PAYMENT_ENTRY_ID = 'paymentEntryId';

    /**
     * @return ?numeric-string
     */
    public function getRate(): ?string
    {
        return $this->get('rate');
    }

    /**
     * @param ?numeric-string $rate
     */
    public function setRate(?string $rate): self
    {
        return $this->set('rate', $rate);
    }

    /**
     * @param ?numeric-string $percentage
     */
    public function setPercentage(?string $percentage): self
    {
        return $this->set(self::FIELD_PERCENTAGE, $percentage);
    }

    public function getComponent(): ?string
    {
        return $this->get('component');
    }

    public function setComponent(string $component): self
    {
        return $this->set('component', $component);
    }

    public function setProduct(Product|Link|null $product): self
    {
        return $this->setRelatedLinkOrEntity(self::FIELD_PRODUCT, $product);
    }

    public function getTaxCode(): TaxCode
    {
        $taxCode =  $this->relations->getOne('taxCode');

        if (!$taxCode instanceof TaxCode) {
            throw new UnexpectedValueException("No tax code.");
        }

        return $taxCode;
    }

    public function setTaxCode(TaxCode $taxCode): self
    {
        return $this->setRelatedLinkOrEntity('taxCode', $taxCode);
    }

    public function setItem(?LinkParent $item): self
    {
        return $this->setValueObject(self::FIELD_ITEM, $item);
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

    public function getAmountLocal(): ?Currency
    {
        $value = $this->getValueObject('amountLocal');

        if (!$value instanceof Currency) {
            return null;
        }

        return $value;
    }

    public function getBaseAmountLocal(): ?Currency
    {
        /** @var ?numeric-string $baseAmount */
        $baseAmount = $this->get('baseAmountLocal');

        if ($baseAmount === null) {
            return null;
        }

        if (!$this->getAmountLocal()) {
            return null;
        }

        return Currency::create($baseAmount, $this->getAmountLocal()->getCode());
    }

    public function setOrder(int $order): self
    {
        return $this->set(self::FIELD_ORDER, $order);
    }

    public function setSource(OrderEntity $source): self
    {
        return $this->setRelatedLinkOrEntity(self::FIELD_SOURCE, $source);
    }

    public function getSource(): OrderEntity
    {
        $source = $this->relations->getOne(self::FIELD_SOURCE);

        if (!$source instanceof OrderEntity) {
            throw new UnexpectedValueException("No source.");
        }

        return $source;
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

    public function setAmountLocal(?Currency $amount): self
    {
        return $this->setValueObject('amountLocal', $amount);
    }

    /**
     * @param ?numeric-string $amount
     */
    public function setBaseAmountLocal(?string $amount): self
    {
        return $this->set('baseAmountLocal', $amount);
    }

    public function getAllocation(): PaymentAllocation
    {
        $allocation = $this->relations->getOne(self::FIELD_ALLOCATION);

        if (!$allocation instanceof PaymentAllocation) {
            throw new UnexpectedValueException("No allocation.");
        }

        return $allocation;
    }

    public function setAllocation(PaymentAllocation|Link $allocation): self
    {
        return $this->setRelatedLinkOrEntity(self::FIELD_ALLOCATION, $allocation);
    }

    public function getPaymentEntry(): PaymentEntry
    {
        $entry = $this->relations->getOne(self::FIELD_PAYMENT_ENTRY);

        if (!$entry instanceof PaymentEntry) {
            throw new UnexpectedValueException("No payment entry.");
        }

        return $entry;
    }

    public function setPaymentEntry(PaymentEntry|Link $entry): self
    {
        return $this->setRelatedLinkOrEntity(self::FIELD_PAYMENT_ENTRY, $entry);
    }
}
