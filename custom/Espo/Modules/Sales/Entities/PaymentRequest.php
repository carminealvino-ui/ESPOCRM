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

use Espo\Core\Di\InjectableFactoryAware;
use Espo\Core\Di\InjectableFactorySetter;
use Espo\Core\Field\Currency;
use Espo\Core\Field\Date;
use Espo\Core\Field\Link;
use Espo\Core\Field\LinkMultiple;
use Espo\Core\Name\Field;
use Espo\Core\ORM\Entity;
use Espo\Core\Utils\Config\ApplicationConfig;
use Espo\Modules\Crm\Entities\Account;
use Espo\ORM\EntityCollection;
use UnexpectedValueException;

class PaymentRequest extends Entity implements InjectableFactoryAware
{
    use InjectableFactorySetter;

    public const ENTITY_TYPE = 'PaymentRequest';

    public const STATUS_DRAFT = 'Draft';
    public const STATUS_PENDING = 'Pending';
    public const STATUS_IN_PROGRESS = 'In Progress';
    public const STATUS_PAID = 'Paid';
    public const STATUS_CANCELED = 'Canceled';
    public const STATUS_EXPIRED = 'Expired';

    public function has(string $attribute): bool
    {
        if ($attribute === 'paymentUrl') {
            return $this->has('referenceId');
        }

        return parent::has($attribute);
    }

    public function get(string $attribute): mixed
    {
        if ($attribute === 'paymentUrl') {
            return $this->getPaymentUrl();
        }

        return parent::get($attribute);
    }

    public function getPaymentUrl(): ?string
    {
        $referenceId = $this->getReferenceId();

        if (!$referenceId) {
            return null;
        }

        $config = $this->injectableFactory->create(ApplicationConfig::class);

        $siteUrl = $config->getSiteUrl();

        return "$siteUrl?entryPoint=payment&id=$referenceId";
    }

    public function getPreviousStatus(): ?string
    {
        return $this->getFetched('status');
    }

    public function getStatus(): string
    {
        return $this->get('status');
    }

    public function setStatus(string $status): self
    {
        return $this->set('status', $status);
    }

    public function isLocked(): bool
    {
        return (bool) $this->get('isLocked');
    }

    public function isNotActual(): bool
    {
        return (bool) $this->get('isNotActual');
    }

    public function getAmount(): Currency
    {
        $amount = $this->getValueObject('amount');

        if (!$amount instanceof Currency) {
            throw new UnexpectedValueException("No amount.");
        }

        return $amount;
    }

    public function setAmount(?Currency $amount): self
    {
        $this->setValueObject('amount', $amount);

        return $this;
    }

    public function getAccount(): ?Account
    {
        /** @var ?Account */
        return $this->relations->getOne('account');
    }

    /**
     * @return EntityCollection<Invoice>
     */
    public function getInvoices(): EntityCollection
    {
        /** @var EntityCollection<Invoice> */
        return $this->relations->getMany('invoices');
    }

    public function getReferenceId(): ?string
    {
        return $this->get('referenceId');
    }

    public function setReferenceId(?string $referenceId): self
    {
        return $this->set('referenceId', $referenceId);
    }

    public function getNumber(): string
    {
        return $this->get('number');
    }

    public function getMethod(): PaymentMethod
    {
        $method = $this->relations->getOne('method');

        if (!$method instanceof PaymentMethod) {
            throw new UnexpectedValueException("No method.");
        }

        return $method;
    }

    public function getExpirationDate(): ?Date
    {
        /** @var ?Date */
        return $this->getValueObject('expirationDate');
    }

    public function setExpirationDate(?Date $date): self
    {
        return $this->setValueObject('expirationDate', $date);
    }

    public function setAccount(?Account $account): self
    {
        return $this->setRelatedLinkOrEntity('account', $account);
    }

    public function setMethod(Link|PaymentMethod $method): self
    {
        return $this->setRelatedLinkOrEntity('method', $method);
    }

    public function getTeams(): LinkMultiple
    {
        /** @var LinkMultiple */
        return $this->getValueObject(Field::TEAMS);
    }

    public function setTeams(LinkMultiple $teams): self
    {
        $this->setValueObject(Field::TEAMS, $teams);

        return $this;
    }

    public function setInvoices(LinkMultiple $invoices): self
    {
        $this->setValueObject('invoices', $invoices);

        return $this;
    }
}
