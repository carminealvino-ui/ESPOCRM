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

namespace Espo\Modules\Sales\Tools\Sales;

use Espo\Core\Field\Currency;

trait HavingLocalAmountsTrait
{
    public function getAmountLocal(): ?Currency
    {
        /** @var ?Currency */
        return $this->getValueObject(OrderEntity::FIELD_AMOUNT_LOCAL);
    }

    public function setAmountLocal(?Currency $currency): self
    {
        return $this->setValueObject(OrderEntity::FIELD_AMOUNT_LOCAL, $currency);
    }

    public function getRoundingAmountLocal(): ?Currency
    {
        $code = $this->getLocalCurrency();

        if (!$code) {
            return null;
        }

        $amount = $this->get(OrderEntity::FIELD_ROUNDING_AMOUNT_LOCAL);

        if ($amount === null) {
            return null;
        }

        return Currency::create($amount, $code);
    }

    public function setRoundingAmountLocal(?Currency $currency): self
    {
        return $this->set(OrderEntity::FIELD_ROUNDING_AMOUNT_LOCAL, $currency?->getAmountAsString());
    }

    public function getShippingAmountLocal(): ?Currency
    {
        $code = $this->getLocalCurrency();

        if (!$code) {
            return null;
        }

        $amount = $this->get(OrderEntity::FIELD_SHIPPING_AMOUNT_LOCAL);

        if ($amount === null) {
            return null;
        }

        return Currency::create($amount, $code);
    }

    public function setShippingAmountLocal(?Currency $currency): self
    {
        return $this->set(OrderEntity::FIELD_SHIPPING_AMOUNT_LOCAL, $currency?->getAmountAsString());
    }

    public function getGrandTotalAmountLocal(): ?Currency
    {
        $code = $this->getLocalCurrency();

        if (!$code) {
            return null;
        }

        $amount = $this->get(OrderEntity::FIELD_GRAND_TOTAL_AMOUNT_LOCAL);

        if ($amount === null) {
            return null;
        }

        return Currency::create($amount, $code);
    }

    public function setGrandTotalAmountLocal(?Currency $currency): self
    {
        return $this->set(OrderEntity::FIELD_GRAND_TOTAL_AMOUNT_LOCAL, $currency?->getAmountAsString());
    }

    public function getTaxAmountLocal(): ?Currency
    {
        $code = $this->getLocalCurrency();

        if (!$code) {
            return null;
        }

        $amount = $this->get(OrderEntity::FIELD_TAX_AMOUNT_LOCAL);

        if ($amount === null) {
            return null;
        }

        return Currency::create($amount, $code);
    }

    public function setTaxAmountLocal(?Currency $currency): self
    {
        return $this->set(OrderEntity::FIELD_TAX_AMOUNT_LOCAL, $currency?->getAmountAsString());
    }
}
