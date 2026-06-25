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
use Espo\Modules\Sales\Tools\Tax\TaxBasis;
use Espo\ORM\EntityCollection;
use UnexpectedValueException;

class Tax extends Entity
{
    public const ENTITY_TYPE = 'Tax';

    public const SHIPPING_MODE_FIXED = 'Fixed';
    public const SHIPPING_MODE_PROPORTIONAL = 'Proportional';

    public const STATUS_ACTIVE = 'Active';

    public const FIELD_BASIS = 'basis';

    public const ATTR_TAX_CODE_ID = 'taxCodeId';

    public function getName(): ?string
    {
        return $this->get('name');
    }

    public function getRate(): ?float
    {
        return $this->get('rate') ?? null;
    }

    public function getShippingMode(): ?string
    {
        return $this->get('shippingMode');
    }

    /**
     * @return TaxBasis
     * @throws UnexpectedValueException
     */
    public function getBasis(): TaxBasis
    {
        $raw = $this->get(self::FIELD_BASIS);

        return TaxBasis::tryFrom($raw) ?? throw new UnexpectedValueException();
    }

    public function setBasis(TaxBasis $basis): self
    {
        return $this->set(self::FIELD_BASIS, $basis->value);
    }

    public function setShippingTaxCode(?TaxCode $taxCode): self
    {
        return $this->setRelatedLinkOrEntity('shippingTaxCode', $taxCode);
    }

    public function getShippingTaxCode(): ?TaxCode
    {
        /** @var ?TaxCode */
        return $this->relations->getOne('shippingTaxCode');
    }

    public function setTaxCode(?TaxCode $taxCode): self
    {
        return $this->setRelatedLinkOrEntity('taxCode', $taxCode);
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

    public function getShippingTaxCodeLink(): ?Link
    {
        /** @var ?Link */
        return $this->getValueObject('shippingTaxCode');
    }

    public function setRate(?float $rate): self
    {
        $this->set('rate', $rate);

        return $this;
    }

    public function setShippingMode(?string $mode): self
    {
        $this->set('shippingMode', $mode);

        return $this;
    }

    /**
     * @return EntityCollection<TaxItemRule>
     */
    public function getItemRules(): EntityCollection
    {
        /** @var EntityCollection<TaxItemRule> */
        return $this->relations->getMany('itemRules');
    }
}
