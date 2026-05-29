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

namespace Espo\Modules\Sales\Tools\Invoice;

use Espo\Core\Field\Currency;
use Espo\Core\Field\Date;
use Espo\Core\Field\Link;
use Espo\Modules\Sales\Entities\InvoiceItem;
use Espo\Modules\Sales\Tools\Sales\OrderItem;

class InvoiceOrderItem extends OrderItem
{
    public function getUnitPrice(): ?Currency
    {
        $amount = $this->get(InvoiceItem::FIELD_UNIT_PRICE);
        $code = $this->get(InvoiceItem::FIELD_UNIT_PRICE . 'Currency');

        if ($amount === null || !$code) {
            return null;
        }

        return Currency::create($amount, $code);
    }

    public function withTaxRate(?float $rate): static
    {
        return $this->with(InvoiceItem::FIELD_TAX_RATE, $rate);
    }

    public function withTaxCode(?Link $taxCode): static
    {
        return $this
            ->with(InvoiceItem::FIELD_TAX_CODE . 'Name', $taxCode?->getName() ?? null)
            ->with(InvoiceItem::FIELD_TAX_CODE . 'Id', $taxCode?->getId() ?? null);
    }

    public function withListPrice(Currency $price): static
    {
        return $this
            ->with(InvoiceItem::FIELD_LIST_PRICE, $price->getAmount())
            ->with(InvoiceItem::FIELD_LIST_PRICE . 'Currency', $price->getCode());
    }

    public function withUnitPrice(Currency $price): static
    {
        return $this
            ->with(InvoiceItem::FIELD_UNIT_PRICE, $price->getAmount())
            ->with(InvoiceItem::FIELD_UNIT_PRICE . 'Currency', $price->getCode());
    }

    public function withAmount(Currency $price): static
    {
        return $this
            ->with(InvoiceItem::FIELD_AMOUNT, $price->getAmount())
            ->with(InvoiceItem::FIELD_AMOUNT . 'Currency', $price->getCode());
    }

    public function withPeriodStartDate(?Date $date): static
    {
        return $this->with(InvoiceItem::FIELD_PERIOD_START_DATE, $date?->toString());
    }

    public function withPeriodEndDate(?Date $date): static
    {
        return $this->with(InvoiceItem::FIELD_PERIOD_END_DATE, $date?->toString());
    }

    public function getPeriodStartDate(): ?Date
    {
        $raw = $this->get(InvoiceItem::FIELD_PERIOD_START_DATE);

        return $raw ? Date::fromString($raw) : null;
    }

    public function getPeriodEndDate(): ?Date
    {
        $raw = $this->get(InvoiceItem::FIELD_PERIOD_END_DATE);

        return $raw ? Date::fromString($raw) : null;
    }

    public function getDescription(): ?string
    {
        return $this->get(InvoiceItem::FIELD_DESCRIPTION);
    }

    /**
     * @return ?numeric-string
     */
    public function getUnitPriceNet(): ?string
    {
        return $this->get(InvoiceItem::FIELD_UNIT_PRICE_NET);
    }
}
