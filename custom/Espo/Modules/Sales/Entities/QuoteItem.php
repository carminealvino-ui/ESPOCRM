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

class QuoteItem extends Entity
{
    public const ENTITY_TYPE = 'QuoteItem';

    public const FIELD_LIST_PRICE = 'listPrice';
    public const FIELD_UNIT_PRICE = 'unitPrice';
    public const FIELD_AMOUNT = 'amount';
    public const FIELD_QUANTITY = 'quantity';
    public const FIELD_UNIT_WEIGHT = 'unitWeight';
    public const FIELD_WEIGHT = 'weight';
    public const FIELD_TAX_RATE = 'taxRate';
    public const FIELD_DISCOUNT = 'discount';
    public const FIELD_TAX_CODE = 'taxCode';
    public const FIELD_AMOUNT_LOCAL = 'amountLocal';
    public const FIELD_PRODUCT = 'product';

    public const ATTR_TAX_CODE_ID = 'taxCodeId';
    public const ATTR_PRODUCT_ID = 'productId';

    private const LINK_TAX_CODE = 'taxCode';

    public function loadAllLinkMultipleFields(): void
    {
        foreach ($this->getAttributeList() as $attribute) {
            if ($this->getAttributeParam($attribute, 'isLinkMultipleIdList')) {
                $field = $this->getAttributeParam($attribute, 'relation');

                $this->loadLinkMultipleField($field);
            }
        }
    }

    public function getName(): ?string
    {
        return $this->get('name');
    }

    public function getOrder(): ?int
    {
        return $this->get('order');
    }

    public function getQuantity(): float
    {
        return (float) $this->get('quantity');
    }

    public function getProduct(): ?Link
    {
        /** @var ?Link */
        return $this->getValueObject('product');
    }

    public function getProductEntity(): ?Product
    {
        /** @var ?Product */
        return $this->relations->getOne('product');
    }

    public function allowFractionalQuantity(): ?bool
    {
        return $this->get('allowFractionalQuantity');
    }

    public function setName(?string $name): self
    {
        $this->set('name', $name);

        return $this;
    }

    public function setQuantity(float $quantity): self
    {
        $this->set('quantity', $quantity);

        return $this;
    }

    public function getTaxCode(): ?TaxCode
    {
        if (!$this->hasAttribute(self::LINK_TAX_CODE . 'Id')) {
            return null;
        }

        /** @var ?TaxCode */
        return $this->relations->getOne(self::LINK_TAX_CODE);
    }
}
