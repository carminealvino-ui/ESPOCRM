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
use Espo\Core\Field\LinkParent;
use Espo\Core\ORM\Entity;
use RuntimeException;

class PaymentAllocation extends Entity
{
    public const ENTITY_TYPE = 'PaymentAllocation';

    public const ATTR_PAYMENT_ENTRY_ID = 'paymentEntryId';
    public const ATTR_CREDIT_NOTE_ID = 'creditNoteId';
    public const ATTR_WRITE_OFF_ENTRY_ID = 'writeOffEntryId';
    public const ATTR_SUPPLIER_CREDIT_ID = 'supplierCreditId';

    public const FIELD_DATE = 'date';
    public const FIELD_FX_GAIN_LOSS = 'fxGainLoss';
    public const FIELD_AMOUNT = 'amount';
    public const FIELD_AMOUNT_LOCAL = 'amountLocal';

    public const LINK_CREDIT_NOTE = 'creditNote';
    public const LINK_PAYMENT_ENTRY = 'paymentEntry';
    public const LINK_WRITE_OFF = 'writeOffEntry';
    public const LINK_SUPPLIER_CREDIT = 'supplierCredit';
    public const LINK_TARGET = 'target';

    public function setCreditNote(CreditNote|LinkParent $creditNote): self
    {
        return $this->setRelatedLinkOrEntity(self::LINK_CREDIT_NOTE, $creditNote);
    }

    public function setPaymentEntry(PaymentEntry|LinkParent $paymentEntry): self
    {
        return $this->setRelatedLinkOrEntity(self::LINK_PAYMENT_ENTRY, $paymentEntry);
    }

    public function setWriteOffEntry(WriteOffEntry|LinkParent $writeOffEntry): self
    {
        return $this->setRelatedLinkOrEntity(self::LINK_WRITE_OFF, $writeOffEntry);
    }

    public function setSupplierCredit(SupplierCredit|LinkParent $supplierCredit): self
    {
        return $this->setRelatedLinkOrEntity(self::LINK_SUPPLIER_CREDIT, $supplierCredit);
    }

    public function getCreditNote(): ?CreditNote
    {
        /** @var ?CreditNote */
        return $this->relations->getOne(self::LINK_CREDIT_NOTE);
    }

    public function getWriteOff(): ?WriteOffEntry
    {
        /** @var ?WriteOffEntry */
        return $this->relations->getOne(self::LINK_WRITE_OFF);
    }

    public function getPaymentEntry(): ?PaymentEntry
    {
        /** @var ?PaymentEntry */
        return $this->relations->getOne(self::LINK_PAYMENT_ENTRY);
    }

    public function getSupplierCredit(): ?SupplierCredit
    {
        /** @var ?SupplierCredit */
        return $this->relations->getOne(self::LINK_SUPPLIER_CREDIT);
    }

    public function getTarget(): Invoice|CreditNote|SupplierBill|SupplierCredit|null
    {
        $target = $this->relations->getOne(self::LINK_TARGET);

        if (
            $target &&
            !$target instanceof Invoice &&
            !$target instanceof CreditNote &&
            !$target instanceof SupplierBill &&
            !$target instanceof SupplierCredit
        ) {
            throw new RuntimeException("Bad target.");
        }

        return $target;
    }

    public function getTargetLink(): ?LinkParent
    {
        /** @var ?LinkParent */
        return $this->getValueObject(self::LINK_TARGET);
    }

    public function setTarget(LinkParent|Invoice|CreditNote|SupplierBill|SupplierCredit $target): self
    {
        return $this->setRelatedLinkOrEntity(self::LINK_TARGET, $target);
    }

    public function getAmount(): Currency
    {
        $value = $this->getValueObject(self::FIELD_AMOUNT);

        if (!$value instanceof Currency) {
            $value = Currency::create('0', 'USD');
        }

        return $value;
    }

    public function setAmount(Currency $amount): self
    {
        $this->setValueObject(self::FIELD_AMOUNT, $amount);

        return $this;
    }

    public function getAmountLocal(): ?Currency
    {
        $value = $this->getValueObject(self::FIELD_AMOUNT_LOCAL);

        if (!$value instanceof Currency) {
            return null;
        }

        return $value;
    }

    public function setAmountLocal(?Currency $amount): self
    {
        return $this->setValueObject(self::FIELD_AMOUNT_LOCAL, $amount);
    }

    public function getFxGainLoss(): ?Currency
    {
        $code = $this->getAmountLocal()?->getCode();

        if (!$code) {
            return null;
        }

        $amount = $this->get(self::FIELD_FX_GAIN_LOSS);

        if ($amount === null) {
            return null;
        }

        return Currency::create($amount, $code);
    }

    public function setFxGainLoss(?Currency $currency): self
    {
        return $this->set(self::FIELD_FX_GAIN_LOSS, $currency?->getAmountAsString());
    }

    public function setDate(Date $date): self
    {
        return $this->setValueObject(self::FIELD_DATE, $date);
    }

    public function getDate(): ?Date
    {
        /** @var ?Date */
        return $this->getValueObject(self::FIELD_DATE);
    }

    public function isAnyAmountChanged(): bool
    {
        return
            $this->isAttributeChanged(self::FIELD_AMOUNT) ||
            $this->isAttributeChanged(self::FIELD_AMOUNT . 'Currency') ||
            $this->isAttributeChanged(self::FIELD_AMOUNT_LOCAL) ||
            $this->isAttributeChanged(self::FIELD_AMOUNT_LOCAL . 'Currency') ||
            $this->isAttributeChanged(self::FIELD_FX_GAIN_LOSS);
    }
}
