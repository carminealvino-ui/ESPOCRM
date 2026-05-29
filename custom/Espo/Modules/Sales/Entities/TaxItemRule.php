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
use UnexpectedValueException;

class TaxItemRule extends Entity
{
    public const ENTITY_TYPE = 'TaxItemRule';

    public const STATUS_ACTIVE = 'Active';

    public const ATTR_TAX_CODE_ID = 'taxCodeId';
    public const ATTR_TAX_ID = 'taxId';

    public function getTax(): Tax
    {
        $tax = $this->relations->getOne('tax');

        if (!$tax instanceof Tax) {
            throw new UnexpectedValueException("No tax.");
        }

        return $tax;
    }

    public function getOrder(): int
    {
        return (int) $this->get('order');
    }

    public function getRate(): ?float
    {
        return $this->get('rate');
    }

    public function getTaxCode(): ?TaxCode
    {
        /** @var ?TaxCode */
        return $this->relations->getOne('taxCode');
    }

    public function getTaxCodeLink(): ?Link
    {
        /** @var ?Link */
        return $this->getValueObject('taxCode');
    }

    public function getClass(): TaxClass
    {
        $class = $this->relations->getOne('class');

        if (!$class instanceof TaxClass) {
            throw new UnexpectedValueException("No tax class in tax item rule.");
        }

        return $class;
    }

    public function setRate(?float $rate): self
    {
        $this->set('rate', $rate);

        return $this;
    }

    public function setClass(TaxClass $class): self
    {
        $this->relations->set('class', $class);

        return $this;
    }

    public function setTax(Tax $tax): self
    {
        $this->relations->set('tax', $tax);

        return $this;
    }
}
