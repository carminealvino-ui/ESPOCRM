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

use Espo\Core\Field\Currency;
use Espo\Core\Field\Date;
use Espo\Core\ORM\Entity;
use Espo\Modules\Sales\Tools\PaymentTerms\PaymentTermsHavingOrder;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use UnexpectedValueException;

class PaymentInstallment extends Entity
{
    public const ENTITY_TYPE = 'PaymentInstallment';

    public const FIELD_ORDER = 'order';
    public const FIELD_SOURCE = 'source';
    public const FIELD_DATE = 'date';
    public const FIELD_AMOUNT = 'amount';
    public const FIELD_AMOUNT_LOCAL = 'amountLocal';
    public const FIELD_PERCENTAGE = 'percentage';
    public const FIELD_STATE = 'state';
    public const FIELD_AMOUNT_DUE = 'amountDue';
    public const FIELD_STATUS = 'status';

    public const STATUS_UNSETTLED = 'Unsettled';
    public const STATUS_PARTIALLY_SETTLED = 'Partially Settled';
    public const STATUS_SETTLED = 'Settled';

    public const STATE_DUE = 'Due';
    public const STATE_PARTIALLY_SETTLED = 'Partially Settled';
    public const STATE_SETTLED = 'Settled';
    public const STATE_CANCELED = 'Canceled';

    public function setOrder(int $order): self
    {
        return $this->set(self::FIELD_ORDER, $order);
    }

    /**
     * @return numeric-string
     */
    public function getPercentage(): string
    {
        /** @var numeric-string */
        return $this->get(self::FIELD_PERCENTAGE) ?? '0';
    }

    public function getStatus(): string
    {
        return $this->get(self::FIELD_STATUS);
    }

    public function setStatus(string $status): self
    {
        return $this->set(self::FIELD_STATUS, $status);
    }

    public function setState(?string $state): self
    {
        return $this->set(self::FIELD_STATE, $state);
    }

    /**
     * @param numeric-string $percentage
     */
    public function setPercentage(string $percentage): self
    {
        return $this->set(self::FIELD_PERCENTAGE, $percentage);
    }

    public function getSource(): Invoice
    {
        $source = $this->relations->getOne(self::FIELD_SOURCE);

        if (!$source instanceof Invoice) {
            throw new UnexpectedValueException();
        }

        return $source;
    }

    public function setSource(OrderEntity & PaymentTermsHavingOrder $source): self
    {
        return $this->setRelatedLinkOrEntity(self::FIELD_SOURCE, $source);
    }

    public function getDate(): Date
    {
        $date = $this->getValueObject(self::FIELD_DATE);

        if (!$date instanceof Date) {
            throw new UnexpectedValueException();
        }

        return $date;
    }

    public function setDate(Date $date): self
    {
        return $this->setValueObject(self::FIELD_DATE, $date);
    }

    public function getAmount(): Currency
    {
        $amount = $this->getValueObject(self::FIELD_AMOUNT);

        if (!$amount instanceof Currency) {
            throw new UnexpectedValueException();
        }

        return $amount;
    }

    public function setAmount(Currency $amount): self
    {
        return $this->setValueObject(self::FIELD_AMOUNT, $amount);
    }

    public function getAmountLocal(): Currency
    {
        $amount = $this->getValueObject(self::FIELD_AMOUNT_LOCAL);

        if (!$amount instanceof Currency) {
            throw new UnexpectedValueException();
        }

        return $amount;
    }

    public function setAmountLocal(Currency $amount): self
    {
        return $this->setValueObject(self::FIELD_AMOUNT_LOCAL, $amount);
    }

    public function setAmountDue(Currency $amount): self
    {
        return $this->setValueObject(self::FIELD_AMOUNT_DUE, $amount);
    }
}
