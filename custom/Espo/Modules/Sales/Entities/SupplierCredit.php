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
use Espo\Core\FieldProcessing\Loader\Params as FieldLoaderParams;
use Espo\Modules\Crm\Entities\Contact;
use Espo\Modules\Sales\Classes\FieldLoaders\Invoice\AmountDue as AmountDueLoader;
use Espo\Modules\Sales\Tools\Payment\Allocation;
use Espo\Modules\Sales\Tools\Sales\HavingCurrencyRateEntity;
use Espo\Modules\Sales\Tools\Sales\HavingCurrencyRateEntityTrait;
use Espo\Modules\Sales\Tools\Sales\HavingLocalAmountsTrait;
use Espo\Modules\Sales\Tools\Sales\IssuableOrder;
use Espo\Modules\Sales\Tools\Sales\IssuableOrderTrait;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\Modules\Sales\Tools\Tax\TaxableOrder;
use Espo\Modules\Sales\Tools\Tax\TaxableOrderTrait;
use Espo\ORM\EntityCollection;
use RuntimeException;
use stdClass;

class SupplierCredit extends OrderEntity implements TaxableOrder, IssuableOrder, HavingCurrencyRateEntity
{
    public const ENTITY_TYPE = 'SupplierCredit';

    use TaxableOrderTrait;
    use IssuableOrderTrait;
    use HavingCurrencyRateEntityTrait;
    use HavingLocalAmountsTrait;

    public const ATTR_ALLOCATIONS = 'allocations';

    public const FIELD_AMOUNT_DUE = 'amountDue';
    public const FIELD_DATE_ISSUED = 'dateIssued';
    public const FIELD_DATE_DUE = 'dateDue';
    public const FIELD_SUPPLIER = 'supplier';
    public const FIELD_BILLING_CONTACT = 'billingContact';
    public const FIELD_SUPPLIER_BILL = 'supplierBill';

    private const RELATION_ALLOCATIONS = 'allocations';

    public const STATUS_DRAFT = 'Draft';
    public const STATUS_ISSUED = 'Issued';
    public const STATUS_RESOLVED = 'Resolved';
    public const STATUS_CANCELED = 'Canceled';

    public function getSupplier(): ?Supplier
    {
        /** @var ?Supplier */
        return $this->relations->getOne(self::FIELD_SUPPLIER);
    }

    public function setSupplier(?Supplier $supplier): self
    {
        return $this->setRelatedLinkOrEntity(self::FIELD_SUPPLIER, $supplier);
    }

    public function getBillingContact(): ?Contact
    {
        /** @var ?Contact */
        return $this->relations->getOne(self::FIELD_BILLING_CONTACT);
    }

    public function getSupplierBill(): ?SupplierBill
    {
        /** @var ?SupplierBill */
        return $this->relations->getOne(self::FIELD_SUPPLIER_BILL);
    }

    public function setSupplierBill(?SupplierBill $supplierBill): self
    {
        return $this->setRelatedLinkOrEntity(self::FIELD_SUPPLIER_BILL, $supplierBill);
    }

    public function getDateDue(): ?Date
    {
        /** @var ?Date */
        return $this->getValueObject(self::FIELD_DATE_DUE);
    }

    public function setDateDue(?Date $date): self
    {
        $this->setValueObject(self::FIELD_DATE_DUE, $date);

        return $this;
    }

    public function getDateIssued(): ?Date
    {
        /** @var ?Date */
        return $this->getValueObject(self::FIELD_DATE_ISSUED);
    }

    public function setDateIssued(?Date $date): self
    {
        $this->setValueObject(self::FIELD_DATE_ISSUED, $date);

        return $this;
    }

    public function getAmount(): ?Currency
    {
        /** @var ?Currency */
        return $this->getValueObject(OrderEntity::FIELD_AMOUNT);
    }

    public function getGrandTotalAmount(): ?Currency
    {
        /** @var ?Currency */
        return $this->getValueObject(OrderEntity::FIELD_GRAND_TOTAL_AMOUNT);
    }

    public function getShippingCost(): ?Currency
    {
        /** @var ?Currency */
        return $this->getValueObject(OrderEntity::FIELD_SHIPPING_COST);
    }

    public function setShippingCost(?Currency $cost): self
    {
        return $this->setValueObject(OrderEntity::FIELD_SHIPPING_COST, $cost);
    }

    /**
     * @param Allocation[] $allocations
     */
    public function setAllocations(array $allocations): static
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

            $this->setInContainerNotWritten(self::ATTR_ALLOCATIONS, $serialized);
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
     * @todo Move to a separate util class? Plus for other entities.
     */
    public static function serializeAllocations(array $allocations): array
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

    public function getAmountDue(): ?Currency
    {
        $loader = new AmountDueLoader($this->entityManager);

        $loader->process($this, FieldLoaderParams::create());

        $raw = $this->get(self::FIELD_AMOUNT_DUE);
        $rawCurrency = $this->get(self::FIELD_AMOUNT_DUE . 'Currency');

        return $raw && $rawCurrency ?
            Currency::create($raw, $rawCurrency) : null;
    }
}
