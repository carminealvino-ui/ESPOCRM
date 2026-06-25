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
use Espo\ORM\EntityCollection;

interface TaxableOrder
{
    public function clearTaxSaveItems(bool $start = false): void;

    public function addTaxLineSaveItem(TaxLineSaveItem $taxLineSaveItem): void;

    /**
     * Returns null if not to be saved.
     *
     * @return ?TaxLineSaveItem[]
     */
    public function getTaxLineSaveItems(): ?array;

    public function addTaxTotalSaveItem(TaxTotalItem $taxTotalItem): void;

    /**
     * Returns null if not to be saved.
     *
     * @return ?TaxTotalItem[]
     */
    public function getTaxTotalSaveItems(): ?array;

    public function setTax(?Tax $tax): TaxableOrder;

    /**
     * @return EntityCollection<TaxLineItem>
     */
    public function getTaxLineItemCollection(): EntityCollection;

    public function getTaxTotalItemCollection(): EntityCollection;

    /**
     * @return TaxTotalLine[]
     */
    public function getTaxTotals(): array;

    public function getShippingAmount(): ?Currency;

    public function setShippingAmount(?Currency $currency): TaxableOrder;

    public function getTaxRate(): ?float;

    public function setTaxRate(?float $taxRate): TaxableOrder;

    public function getShippingTaxMode(): ?string;

    public function setShippingTaxMode(?string $mode): TaxableOrder;

    public function getTax(): ?Tax;

    public function setTaxAmount(?Currency $taxAmount): TaxableOrder;

    public function getTaxAmount(): ?Currency;
}
