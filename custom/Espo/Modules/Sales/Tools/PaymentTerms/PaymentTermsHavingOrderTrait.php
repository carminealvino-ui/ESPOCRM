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

namespace Espo\Modules\Sales\Tools\PaymentTerms;

use Espo\Core\Field\Link;
use Espo\Modules\Sales\Entities\PaymentInstallment;
use Espo\Modules\Sales\Entities\PaymentTermsProfile;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\ORM\EntityCollection;
use LogicException;
use UnexpectedValueException;

trait PaymentTermsHavingOrderTrait
{
    /** @var ?InstallmentLine[] */
    private ?array $installmentSaveItems = null;

    public function getPaymentTermsNote(): ?string
    {
        return $this->get(OrderEntity::FIELD_PAYMENT_TERMS_NOTE);
    }

    public function setPaymentTermsNote(?string $note): self
    {
        return $this->set(OrderEntity::FIELD_PAYMENT_TERMS_NOTE, $note);
    }

    public function getPaymentTermsProfile(): ?PaymentTermsProfile
    {
        /** @var ?PaymentTermsProfile */
        return $this->relations->getOne(OrderEntity::FIELD_PAYMENT_TERMS_PROFILE);
    }

    public function setPaymentTermsProfile(PaymentTermsProfile|Link|null $profile): self
    {
        return $this->setRelatedLinkOrEntity(OrderEntity::FIELD_PAYMENT_TERMS_PROFILE, $profile);
    }

    public function isPaymentTermsProfileChanged(): bool
    {
        return $this->isAttributeChanged(OrderEntity::FIELD_PAYMENT_TERMS_PROFILE . 'Id');
    }

    public function loadInstallments(): void
    {
        $value = [];

        foreach ($this->getInstallments() as $line) {
            $value[] = (object) [
                'date' => $line->date->toString(),
                'amount' => $line->amount->getAmountAsString(),
                'amountCurrency' => $line->amount->getCode(),
                'status' => $line->status,
            ];
        }

        $this->setInContainerNotWritten(OrderEntity::FIELD_INSTALLMENTS, $value);
        $this->setFetched(OrderEntity::FIELD_INSTALLMENTS, $value);
    }

    /**
     * @return EntityCollection<PaymentInstallment>
     */
    public function getInstallmentCollection(): EntityCollection
    {
        /** @var EntityCollection<PaymentInstallment> */
        return $this->relations->getMany('installments');
    }

    /**
     * @return InstallmentLine[]
     */
    public function getInstallments(): array
    {
        $items = [];

        foreach ($this->getInstallmentCollection() as $entity) {
            try {
                $items[] = InstallmentLine::fromEntity($entity);
            } catch (UnexpectedValueException) {
                continue;
            }
        }

        return $items;
    }

    /**
     * @param bool $start Pass true before starting adding items.
     */
    public function clearInstallmentSaveItems(bool $start = false): void
    {
        if (!$start) {
            $this->installmentSaveItems = null;

            return;
        }

        $this->installmentSaveItems = [];
    }

    public function addInstallmentSaveItem(InstallmentLine $line): void
    {
        if ($this->installmentSaveItems === null) {
            throw new LogicException("Cannot add payment term line items.");
        }

        $this->installmentSaveItems[] = $line;
    }

    /**
     * @return ?InstallmentLine[]
     */
    public function getInstallmentSaveItems(): ?array
    {
        return $this->installmentSaveItems;
    }
}
