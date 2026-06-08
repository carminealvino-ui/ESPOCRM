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

use Espo\Core\ORM\Entity;

class EuTaxMapping extends Entity
{
    public const ENTITY_TYPE = 'EuTaxMapping';

    public const FIELD_ORDER = 'order';
    public const FIELD_TAX_CODE = 'taxCode';
    public const FIELD_TAX_CLASS = 'taxClass';
    private const FIELD_CATEGORY_CODE = 'categoryCode';
    private const FIELD_EXEMPTION_CODE = 'exemptionCode';
    private const FIELD_EXEMPTION_REASON = 'exemptionReason';

    public const CATEGORY_CODE_E = 'E';

    public function getCategoryCode(): ?string
    {
        return $this->get(self::FIELD_CATEGORY_CODE);
    }

    public function setCategoryCode(?string $code): self
    {
        return $this->set(self::FIELD_CATEGORY_CODE, $code);
    }

    public function getExemptionCode(): ?string
    {
        return $this->get(self::FIELD_EXEMPTION_CODE);
    }

    public function setExemptionCode(?string $code): self
    {
        return $this->set(self::FIELD_EXEMPTION_CODE, $code);
    }

    public function getExemptionReason(): ?string
    {
        return $this->get(self::FIELD_EXEMPTION_REASON);
    }

    public function setExemptionReason(?string $reason): self
    {
        return $this->set(self::FIELD_EXEMPTION_REASON, $reason);
    }

    /**
     * @param int $order
     */
    public function setOrder(int $order): self
    {
        return $this->set(self::FIELD_ORDER, $order);
    }

    public function getOrder(): int
    {
        return (int) $this->get(self::FIELD_ORDER);
    }

    public function setTaxCode(TaxCode $taxCode): self
    {
        return $this->setRelatedLinkOrEntity(self::FIELD_TAX_CODE, $taxCode);
    }

    public function setTaxClass(TaxClass $taxClass): self
    {
        return $this->setRelatedLinkOrEntity(self::FIELD_TAX_CLASS, $taxClass);
    }

    public function getTaxClass(): ?TaxClass
    {
        /** @var ?TaxClass */
        return $this->relations->getOne(self::FIELD_TAX_CLASS);
    }
}
