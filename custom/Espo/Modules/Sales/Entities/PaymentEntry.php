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
use Espo\Core\Field\DateTime;
use Espo\Core\Field\LinkMultiple;
use Espo\Core\Field\LinkParent;
use Espo\Core\Name\Field;
use Espo\Core\ORM\Entity;
use Espo\Modules\Crm\Entities\Account;
use Espo\Modules\Sales\Tools\Payment\Allocation;
use Espo\Modules\Sales\Tools\Payment\PartyType;
use Espo\Modules\Sales\Tools\Payment\Type;
use Espo\Modules\Sales\Tools\Sales\IssuableOrder;
use Espo\Modules\Sales\Tools\Sales\IssuableOrderTrait;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\ORM\EntityCollection;
use RuntimeException;
use stdClass;
use UnexpectedValueException;

class PaymentEntry extends Entity implements IssuableOrder
{
    use IssuableOrderTrait;

    public const ENTITY_TYPE = 'PaymentEntry';

    public const STATUS_DRAFT = 'Draft';
    public const STATUS_IN_PROGRESS = 'In Progress';
    public const STATUS_PAID = 'Paid';
    public const STATUS_COMPLETED = 'Completed';
    public const STATUS_CANCELED = 'Canceled';

    public const TYPE_INBOUND = 'Inbound';
    public const TYPE_OUTBOUND = 'Outbound';

    public const ATTR_ALLOCATIONS = 'allocations';
    public const ATTR_METHOD_ID = 'methodId';

    public const FIELD_STATUS = 'status';
    public const FIELD_TYPE = 'type';
    public const FIELD_PARTY_TYPE = 'partyType';
    public const FIELD_DATE_PAID = 'datePaid';
    public const FIELD_AMOUNT_LOCAL = 'amountLocal';
    public const FIELD_ACCOUNT = 'account';
    public const FIELD_SUPPLIER = 'supplier';

    public const RELATION_ALLOCATIONS = 'allocations';

    public function isLocked(): bool
    {
        return (bool) $this->get('isLocked');
    }

    public function getType(): Type
    {
        $raw = $this->get(self::FIELD_TYPE) ?? Type::Inbound->value;

        return Type::tryFrom($raw) ?? Type::Inbound;
    }

    public function setType(Type $type): self
    {
        return $this->set(self::FIELD_TYPE, $type->value);
    }

    public function getPartyType(): PartyType
    {
        $raw = $this->get(self::FIELD_PARTY_TYPE) ?? PartyType::Customer->value;

        return PartyType::tryFrom($raw) ?? PartyType::Customer;
    }

    public function setPartyType(PartyType $partyType): self
    {
        return $this->set(self::FIELD_PARTY_TYPE, $partyType->value);
    }

    public function isDone(): bool
    {
        return $this->get('isDone');
    }

    public function isNotActual(): bool
    {
        return (bool) $this->get('isNotActual');
    }

    public function getStatus(): string
    {
        return $this->get(self::FIELD_STATUS);
    }

    public function getFetchedStatus(): ?string
    {
        return $this->getFetched(self::FIELD_STATUS);
    }

    public function getNumber(): ?string
    {
        return $this->get('number');
    }

    public function setNumber(?string $number): self
    {
        return $this->set('number', $number);
    }

    public function setStatus(string $status): self
    {
        return $this->set('status', $status);
    }

    public function setMethod(?PaymentMethod $method): self
    {
        return $this->setRelatedLinkOrEntity('method', $method);
    }

    public function getDatePaid(): ?Date
    {
        /** @var ?Date */
        return $this->getValueObject('datePaid');
    }

    public function setDatePaid(?Date $datePaid): self
    {
        return $this->setValueObject('datePaid', $datePaid);
    }

    public function setRequest(?PaymentRequest $request): self
    {
        return $this->setRelatedLinkOrEntity('request', $request);
    }

    public function getMethod(): ?PaymentMethod
    {
        /** @var ?PaymentMethod */
        return $this->relations->getOne('method');
    }

    public function getRequest(): ?PaymentRequest
    {
        /** @var ?PaymentRequest */
        return $this->relations->getOne('request');
    }

    public function getAmount(): Currency
    {
        $value = $this->getValueObject('amount');

        if (!$value instanceof Currency) {
            $value = Currency::create('0', 'USD');
        }

        return $value;
    }

    public function setAmount(Currency $amount): self
    {
        $this->setValueObject('amount', $amount);

        return $this;
    }

    public function getAccount(): ?Account
    {
        /** @var ?Account */
        return $this->relations->getOne(self::FIELD_ACCOUNT);
    }

    public function setAccount(?Account $account): self
    {
        return $this->setRelatedLinkOrEntity(self::FIELD_ACCOUNT, $account);
    }

    public function getSupplier(): ?Supplier
    {
        /** @var ?Supplier */
        return $this->relations->getOne(self::FIELD_SUPPLIER);
    }

    public function setSupplier(?Account $supplier): self
    {
        return $this->setRelatedLinkOrEntity(self::FIELD_SUPPLIER, $supplier);
    }

    /**
     * @param Allocation[] $allocations
     */
    public function setAllocations(array $allocations): self
    {
        $this->set(self::ATTR_ALLOCATIONS, self::serializeAllocations($allocations));

        return $this;
    }

    public function isAllocationsChanged(): bool
    {
        if (!$this->hasFetched(self::ATTR_ALLOCATIONS)) {
            $this->getFetchedAllocations();
        }

        return $this->isAttributeChanged(self::ATTR_ALLOCATIONS);
    }

    /**
     * @return Allocation[]
     * @throws RuntimeException
     */
    public function getFetchedAllocations(): array
    {
        if ($this->isNew()) {
            return [];
        }

        if (!$this->hasFetched(self::ATTR_ALLOCATIONS)) {
            $serialized = self::serializeAllocations($this->loadAllocations());

            $this->setFetched(self::ATTR_ALLOCATIONS, $serialized);
        }

        $serialized = $this->getFetched(self::ATTR_ALLOCATIONS) ?? [];

        return self::unserializeAllocations($serialized);
    }

    /**
     * @return Allocation[]
     * @throws RuntimeException
     */
    public function getAllocations(): array
    {
        if (!$this->has(self::ATTR_ALLOCATIONS) && !$this->isNew()) {
            $allocations = $this->loadAllocations();

            $serialized = self::serializeAllocations($allocations);

            /** @phpstan-ignore-next-line function.alreadyNarrowedType */
            if (method_exists($this, 'setInContainerNotWritten')) {
                $this->setInContainerNotWritten(self::ATTR_ALLOCATIONS, $serialized);
            } else {
                $this->setInContainer(self::ATTR_ALLOCATIONS, $serialized);
            }

            $this->setFetched(self::ATTR_ALLOCATIONS, $serialized);

            return $allocations;
        }

        /** @var stdClass[] $serialized */
        $serialized = $this->has(self::ATTR_ALLOCATIONS) ?
            ($this->get(self::ATTR_ALLOCATIONS) ?? []) :
            [];

        return self::unserializeAllocations($serialized);
    }

    /**
     * @return Allocation[]
     */
    private function loadAllocations(): array
    {
        $list = [];

        /** @var EntityCollection<PaymentAllocation> $collection */
        $collection = $this->relations->getMany(self::RELATION_ALLOCATIONS);

        foreach ($collection as $entity) {
            $target = $entity->getTarget();

            if ($target) {
                $targetLink = LinkParent::createFromEntity($target)->withName($target->getName());
            } else {
                $targetLink = $entity->getTargetLink();
            }

            if (!$targetLink) {
                continue;
            }

            $amount = $entity->getAmount();

            if ($amount->getCode() !== $this->getAmount()->getCode()) {
                $amount = Currency::create($amount->getAmountAsString(), $this->getAmount()->getCode());
            }

            $list[] = new Allocation($targetLink, $amount);
        }

        return $list;
    }

    /**
     * @param Allocation[] $allocations
     * @return stdClass[]
     */
    private static function serializeAllocations(array $allocations): array
    {
        $output = [];

        foreach ($allocations as $allocation) {
            $output[] = (object) [
                'targetType' => $allocation->getTarget()->getEntityType(),
                'targetId' => $allocation->getTarget()->getId(),
                'targetName' => $allocation->getTarget()->getName(),
                'amount' => $allocation->getAmount()->getAmountAsString(),
                'amountCurrency' => $allocation->getAmount()->getCode(),
            ];
        }

        return $output;
    }

    /**
     * @param stdClass[] $serialized
     * @return Allocation[]
     */
    private static function unserializeAllocations(array $serialized): array
    {
        $output = [];

        foreach ($serialized as $item) {
            $output[] = self::unserializeAllocation($item);
        }

        return $output;
    }

    private static function unserializeAllocation(stdClass $item): Allocation
    {
        $targetType = $item->targetType ?? null;
        $targetId = $item->targetId ?? null;
        $targetName = $item->targetName ?? null;
        $amountRaw = $item->amount ?? null;
        $amountCurrency = $item->amountCurrency ?? 'USD';

        if (!is_string($targetType)) {
            throw new RuntimeException("Bad allocation target type.");
        }

        if (!is_string($targetId)) {
            throw new RuntimeException("Bad allocation target ID.");
        }

        if ($targetName !== null && !is_string($targetName)) {
            throw new RuntimeException("Bad allocation target name.");
        }

        if (!is_string($amountRaw) && !is_numeric($amountRaw)) {
            throw new RuntimeException("Bad allocation amount.");
        }

        if (!is_string($amountCurrency)) {
            throw new RuntimeException("Bad allocation currency.");
        }

        /** @var numeric-string|float|int $amountRaw */

        if (is_int($amountRaw)) {
            $amountRaw = (float) $amountRaw;
        }

        $amount = Currency::create($amountRaw, $amountCurrency);
        $target = LinkParent::create($targetType, $targetId)->withName($targetName);

        return new Allocation($target, $amount);
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

    public function getModifiedAt(): ?DateTime
    {
        /** @var ?DateTime */
        return $this->getValueObject('modifiedAt');
    }

    public function setIsLocked(bool $isLocked): static
    {
        return $this->set('isLocked', $isLocked);
    }

    /**
     * @return ?numeric-string
     */
    public function getCurrencyRate(): ?string
    {
        /** @var ?numeric-string */
        return $this->get(OrderEntity::FIELD_CURRENCY_RATE);
    }

    /**
     * @param ?numeric-string $rate
     */
    public function setCurrencyRate(?string $rate): self
    {
        return $this->set(OrderEntity::FIELD_CURRENCY_RATE, $rate);
    }

    public function isCurrencyRateChanged(): bool
    {
        return $this->isAttributeChanged(OrderEntity::FIELD_CURRENCY_RATE);
    }

    public function getLocalCurrency(): ?string
    {
        return $this->get(OrderEntity::ATTR_AMOUNT_LOCAL_CURRENCY);
    }

    public function setLocalCurrency(?string $code): self
    {
        return $this->set(OrderEntity::ATTR_AMOUNT_LOCAL_CURRENCY, $code);
    }

    public function getAmountLocal(): ?Currency
    {
        /** @var ?Currency */
        return $this->getValueObject(self::FIELD_AMOUNT_LOCAL);
    }

    public function setAmountLocal(?Currency $currency): self
    {
        return $this->setValueObject(self::FIELD_AMOUNT_LOCAL, $currency);
    }

    public function getAmountCurrency(): string
    {
        return $this->get(OrderEntity::ATTR_AMOUNT_CURRENCY) ??
            throw new UnexpectedValueException("No amount currency.");
    }
}
