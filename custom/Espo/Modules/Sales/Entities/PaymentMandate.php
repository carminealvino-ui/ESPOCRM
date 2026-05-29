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
use Espo\Core\ORM\Entity;
use Espo\ORM\Entity as OrmEntity;
use stdClass;
use UnexpectedValueException;

class PaymentMandate extends Entity
{
    public const ENTITY_TYPE = 'PaymentMandate';

    public const STATUS_ACTIVE = 'Active';

    public function getReferenceId(): string
    {
        return (string) $this->get('referenceId');
    }

    public function getAccountHolder(): string
    {
        return (string) $this->get('accountHolder');
    }

    public function getIban(): string
    {
        return (string) $this->get('iban');
    }

    public function getDateSigned(): ?Date
    {
        /** @var ?Date */
        return $this->getValueObject('dateSigned');
    }

    public function getData(): ?stdClass
    {
        return $this->get('data');
    }

    public function getType(): string
    {
        $value = $this->get('type');

        if (!is_string($value)) {
            throw new UnexpectedValueException("No type.");
        }

        return $value;
    }

    public function hasRecord(): bool
    {
        $entity = $this->relations->getOne('record');

        return $entity !== null;
    }

    public function getRecord(): Entity
    {
        $entity = $this->relations->getOne('record');

        if (!$entity instanceof Entity) {
            throw new UnexpectedValueException("No record.");
        }

        return $entity;
    }

    public function setRecord(OrmEntity $record): self
    {
        $this->relations->set('record', $record);

        return $this;
    }
}
