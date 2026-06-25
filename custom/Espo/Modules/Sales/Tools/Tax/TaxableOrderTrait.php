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

namespace Espo\Modules\Sales\Tools\Tax;

use Espo\Core\Field\Currency;
use Espo\Modules\Sales\Entities\Tax;
use Espo\Modules\Sales\Entities\TaxLineItem;
use Espo\Modules\Sales\Entities\TaxTotalItem;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\ORM\EntityCollection;
use LogicException;

trait TaxableOrderTrait
{
    /** @var ?TaxLineSaveItem[] $taxLineSaveItems */
    private ?array $taxLineSaveItems = null;
    /** @var ?TaxTotalItem[] $taxTotalSaveItems */
    private ?array $taxTotalSaveItems = null;

    public const FIELD_TAX_TOTALS = 'taxTotals';

    /**
     * @param bool $start Pass true before starting adding items.
     */
    public function clearTaxSaveItems(bool $start = false): void
    {
        if (!$start) {
            $this->taxLineSaveItems = null;
            $this->taxTotalSaveItems = null;

            return;
        }

        $this->taxLineSaveItems = [];
        $this->taxTotalSaveItems = [];
    }

    public function addTaxLineSaveItem(TaxLineSaveItem $taxLineSaveItem): void
    {
        if ($this->taxLineSaveItems === null) {
            throw new LogicException("Cannot add tax line items.");
        }

        $this->taxLineSaveItems[] = $taxLineSaveItem;
    }

    /**
     * @return ?TaxLineSaveItem[]
     */
    public function getTaxLineSaveItems(): ?array
    {
        return $this->taxLineSaveItems;
    }

    public function addTaxTotalSaveItem(TaxTotalItem $taxTotalItem): void
    {
        if ($this->taxTotalSaveItems === null) {
            throw new LogicException("Cannot add tax total items.");
        }

        $this->taxTotalSaveItems[] = $taxTotalItem;
    }

    /**
     * @return ?TaxTotalItem[]
     */
    public function getTaxTotalSaveItems(): ?array
    {
        return $this->taxTotalSaveItems;
    }

    public function getTax(): ?Tax
    {
        /** @var ?Tax */
        return $this->relations->getOne('tax');
    }

    public function setTax(?Tax $tax): self
    {
        return $this->setRelatedLinkOrEntity('tax', $tax);
    }

    /**
     * @return EntityCollection<TaxLineItem>
     */
    public function getTaxLineItemCollection(): EntityCollection
    {
        /** @var EntityCollection<TaxLineItem> */
        return $this->relations->getMany('taxLineItems');
    }

    /**
     * @return EntityCollection<TaxTotalItem>
     */
    public function getTaxTotalItemCollection(): EntityCollection
    {
        /** @var EntityCollection<TaxTotalItem> */
        return $this->relations->getMany('taxTotalItems');
    }

    /**
     * @return TaxTotalLine[]
     */
    public function getTaxTotals(): array
    {
        $output = [];

        foreach ($this->getTaxTotalItemCollection() as $item) {
            $output[] = new TaxTotalLine(
                taxCode: $item->getTaxCode(),
                amount: $item->getAmount(),
                baseAmount: $item->getBaseAmount(),
                amountLocal: $item->getAmountLocal(),
                baseAmountLocal: $item->getBaseAmountLocal(),
            );
        }

        return $output;
    }

    public function loadTaxTotals(): void
    {
        $value = [];

        foreach ($this->getTaxTotals() as $item) {
            $value[] = (object) [
                'name' => $item->taxCode->getName(),
                'codeId' => $item->taxCode->getId(),
                'codeName' => $item->taxCode->getCode(),
                'label' => $item->taxCode->getLabel(),
                'amount' => $item->amount->getAmountAsString(),
                'amountCurrency' => $item->amount->getCode(),
                'amountLocal' => $item->amountLocal?->getAmountAsString(),
                'amountLocalCurrency' => $item->amountLocal?->getCode(),
                'baseAmount' => $item->baseAmount->getAmountAsString(),
                'baseAmountCurrency' => $item->baseAmount->getCode(),
                'baseAmountLocal' => $item->baseAmountLocal?->getAmountAsString(),
                'baseAmountLocalCurrency' => $item->baseAmountLocal?->getCode(),
            ];
        }

        $this->setInContainerNotWritten(self::FIELD_TAX_TOTALS, $value);
        $this->setFetched(self::FIELD_TAX_TOTALS, $value);
    }

    public function getShippingAmount(): ?Currency
    {
        /** @var Currency */
        return $this->getValueObject(OrderEntity::FIELD_SHIPPING_AMOUNT);
    }

    public function setShippingAmount(?Currency $amount): self
    {
        return $this->setValueObject(OrderEntity::FIELD_SHIPPING_AMOUNT, $amount);
    }

    public function getTaxRate(): ?float
    {
        return $this->get('taxRate');
    }

    public function setTaxRate(?float $taxRate): self
    {
        $this->set('taxRate', $taxRate);

        return $this;
    }

    public function getShippingTaxMode(): ?string
    {
        return $this->get('shippingTaxMode');
    }

    public function setShippingTaxMode(?string $mode): self
    {
        $this->set('shippingTaxMode', $mode);

        return $this;
    }

    public function getTaxAmount(): ?Currency
    {
        /** @var Currency */
        return $this->getValueObject(OrderEntity::FIELD_TAX_AMOUNT);
    }

    public function setTaxAmount(?Currency $amount): self
    {
        return $this->setValueObject(OrderEntity::FIELD_TAX_AMOUNT, $amount);
    }
}
