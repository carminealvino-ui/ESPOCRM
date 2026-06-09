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

use Espo\Core\Field\Date;
use Espo\Core\Field\Link;
use Espo\Core\ORM\Entity;
use RuntimeException;

class InventoryNumber extends Entity
{
    public const ENTITY_TYPE = 'InventoryNumber';

    public const TYPE_BATCH = 'Batch';
    public const TYPE_SERIAL = 'Serial';

    public function getName(): string
    {
        return $this->get('name');
    }

    /**
     * @return self::TYPE_BATCH|self::TYPE_SERIAL|string
     */
    public function getType(): string
    {
        return $this->get('type');
    }

    public function getProduct(): Link
    {
        /** @var ?Link $value */
        $value = $this->getValueObject('product');

        if (!$value) {
            /** @noinspection PhpDeprecationInspection */
            throw new RuntimeException("No product in inventory number '$this->id'.");
        }

        return $value;
    }

    public function setName(string $name): self
    {
        $this->set('name', $name);

        return $this;
    }

    public function setProductId(string $productId): self
    {
        $this->set('productId', $productId);

        return $this;
    }

    public function setType(string $type): self
    {
        $this->set('type', $type);

        return $this;
    }

    public function getIncomingDate(): ?Date
    {
        /** @var ?Date */
        return $this->getValueObject('incomingDate');
    }

    public function setIncomingDate(?Date $incomingDate): self
    {
        $this->setValueObject('incomingDate', $incomingDate);

        return $this;
    }

    public function setExpirationDate(?Date $expirationDate): self
    {
        $this->setValueObject('expirationDate', $expirationDate);

        return $this;
    }
}
