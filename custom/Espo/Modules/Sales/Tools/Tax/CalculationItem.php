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
use Espo\Modules\Sales\Entities\TaxCode;

readonly class CalculationItem
{
    /**
     * @param ?numeric-string $rate
     */
    public function __construct(
        public Currency $amount,
        public Currency $baseAmount,
        public TaxCode $taxCode,
        public Currency $amountPrecise,
        public ?string $rate,
        public bool $isInPrice = false,
    ) {}

    public function withAddedAmount(Currency $amount): self
    {
        return new self(
            amount: $this->amount->add($amount),
            baseAmount: $this->baseAmount,
            taxCode: $this->taxCode,
            amountPrecise: $this->amountPrecise,
            rate: $this->rate,
            isInPrice: $this->isInPrice,
        );
    }
    public function withIsInPrice(bool $isInPrice): self
    {
        return new self(
            amount: $this->amount,
            baseAmount: $this->baseAmount,
            taxCode: $this->taxCode,
            amountPrecise: $this->amountPrecise,
            rate: $this->rate,
            isInPrice: $isInPrice,
        );
    }
}
