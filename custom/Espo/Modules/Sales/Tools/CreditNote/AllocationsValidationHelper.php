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

namespace Espo\Modules\Sales\Tools\CreditNote;

use Espo\Core\Acl;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Field\Currency;
use Espo\Modules\Sales\Entities\CreditNote;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Entities\PaymentAllocation;
use Espo\Modules\Sales\Entities\PaymentEntry;
use Espo\Modules\Sales\Entities\SupplierBill;
use Espo\Modules\Sales\Entities\SupplierCredit;
use Espo\Modules\Sales\Entities\WriteOffEntry;
use Espo\Modules\Sales\Tools\Payment\Allocation;
use Espo\Modules\Sales\Tools\Payment\AllocationHelper;
use Espo\Modules\Sales\Tools\Payment\PartyType;
use Espo\Modules\Sales\Tools\Payment\Type;
use Espo\Modules\Sales\Tools\Quote\BeforeSaveProcessor;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use RuntimeException;

class AllocationsValidationHelper
{
    public function __construct(
        private EntityManager $entityManager,
        private Acl $acl,
        private BeforeSaveProcessor $beforeSaveProcessor,
    ) {}

    /**
     * @throws BadRequest
     * @throws Forbidden
     */
    public function validate(CreditNote|WriteOffEntry|SupplierCredit|PaymentEntry $entry, bool $inHook = false): void
    {
        $this->validateAllocationsNoDraft($entry);

        if (!$entry->isAttributeChanged(CreditNote::ATTR_ALLOCATIONS)) {
            if ($entry->isAttributeChanged(OrderEntity::FIELD_STATUS)) {
                $this->validateAmount($entry, $inHook);
            }

            return;
        }

        $this->validateNoSameTarget($entry);

        foreach (AllocationHelper::getRemovedAllocations($entry) as $allocation) {
            $this->validateRemovedAllocation($allocation);
        }

        foreach ($entry->getAllocations() as $allocation) {
            $this->validateAllocation($entry, $allocation, $inHook);
        }

        $this->validateAmount($entry, $inHook);
    }

    /**
     * @throws BadRequest
     * @throws Forbidden
     */
    private function validateAllocation(
        CreditNote|WriteOffEntry|SupplierCredit|PaymentEntry $entry,
        Allocation $allocation,
        bool $inHook,
    ): void {

        $target = $this->fetchTarget($allocation);

        if (!$target) {
            $this->throwBadRequest('allocationTargetNotFound');
        }

        if (
            (
                !$target instanceof Invoice &&
                !$target instanceof CreditNote &&
                !$target instanceof SupplierBill &&
                !$target instanceof SupplierCredit
            ) ||
            $entry instanceof CreditNote && !$target instanceof Invoice ||
            $entry instanceof WriteOffEntry && !$target instanceof Invoice ||
            $entry instanceof SupplierCredit && !$target instanceof SupplierBill ||
            $entry instanceof PaymentEntry && $this->isWrongPaymentEntryTarget($entry, $target)
        ) {
            $this->throwBadRequest('allocationTargetNotAllowed');
        }

        $isChangedOrAdded = AllocationHelper::isAllocationChangedOrAdded($entry, $allocation);

        if ($isChangedOrAdded && !$inHook && !$this->acl->checkEntityEdit($target)) {
            $this->throwForbidden('noEditAccessToAllocationTarget');
        }

        if ($isChangedOrAdded && $target->isLocked()) {
            $this->throwForbidden('allocationTargetIsLocked');
        }

        if ($isChangedOrAdded && $target->isNotActual()) {
            $this->throwForbidden('allocationTargetIsNotOpen');
        }

        if (
            !$target->getAmount() ||
            $target->getAmount()->getCode() !== $entry->getAmount()?->getCode()
        ) {
            $this->throwForbidden('allocationTargetCurrencyMismatch');
        }

        if (
            !$entry instanceof WriteOffEntry &&
            $entry->getLocalCurrency() &&
            $target->getLocalCurrency() &&
            $entry->getLocalCurrency() !== $target->getLocalCurrency()
        ) {
            $this->throwForbidden('allocationTargetLocalCurrencyMismatch');
        }

        if (
            $entry instanceof SupplierCredit ||
            $entry instanceof PaymentEntry && $entry->getPartyType() === PartyType::Supplier
        ) {
            if (
                ($target instanceof SupplierBill || $target instanceof SupplierCredit) &&
                $target->getSupplier()?->getId() !== $entry->getSupplier()?->getId()
            ) {
                $this->throwForbidden('allocationTargetSupplierMismatch');
            }
        } else if ($target->getAccount()?->getId() !== $entry->getAccount()?->getId()) {
            $this->throwForbidden('allocationTargetAccountMismatch');
        }
    }

    private function isWrongPaymentEntryTarget(PaymentEntry $entry, Entity $target): bool
    {
        if ($entry->getPartyType() === PartyType::Supplier) {
            $isWrong =
                $entry->getType() === Type::Inbound && !$target instanceof SupplierCredit ||
                $entry->getType() === Type::Outbound && !$target instanceof SupplierBill;
        } else {
            $isWrong =
                $entry->getType() === Type::Inbound && !$target instanceof Invoice ||
                $entry->getType() === Type::Outbound && !$target instanceof CreditNote;
        }

        return $isWrong;
    }

    private function fetchTarget(Allocation $allocation): ?Entity
    {
        $targetLink = $allocation->getTarget();

        return $this->entityManager->getEntityById($targetLink->getEntityType(), $targetLink->getId());
    }

    /**
     * @throws BadRequest
     */
    private function validateAllocationsNoDraft(CreditNote|WriteOffEntry|SupplierCredit|PaymentEntry $entry): void
    {
        $isChanged =
            $entry->isAttributeChanged(OrderEntity::FIELD_STATUS) ||
            $entry->isAttributeChanged(CreditNote::ATTR_ALLOCATIONS);

        if (!$isChanged) {
            return;
        }

        if (
            // The draft status value is the same for all entity types.
            $entry->getStatus() === CreditNote::STATUS_DRAFT &&
            $entry->getAllocations()
        ) {
            $this->throwBadRequest('draftCannotHaveAllocations');
        }
    }

    /**
     * @throws BadRequest
     */
    private function validateNoSameTarget(CreditNote|WriteOffEntry|SupplierCredit|PaymentEntry $entry): void
    {
        $metMap = [];

        foreach ($entry->getAllocations() as $allocation) {
            $key = $allocation->getTarget()->getEntityType() . '_' . $allocation->getTarget()->getId();

            if (isset($metMap[$key])) {
                $this->throwBadRequest('cannotAllocateMoreThanOnce');
            }

            $metMap[$key] = true;
        }
    }

    /**
     * @throws Forbidden
     */
    private function validateRemovedAllocation(Allocation $allocation): void
    {
        $target = $this->fetchTarget($allocation);

        if (!$target) {
            return;
        }

        if (
            !$target instanceof Invoice &&
            !$target instanceof SupplierBill &&
            !$target instanceof CreditNote &&
            !$target instanceof SupplierCredit
        ) {
            return;
        }

        if ($target->isLocked()) {
            $this->throwForbidden('removedAllocationTargetIsLocked');
        }

        if ($target->isNotActual()) {
            $this->throwForbidden('removedAllocationTargetIsNotOpen');
        }
    }

    /**
     * @throws Forbidden
     */
    private function validateAmount(CreditNote|WriteOffEntry|SupplierCredit|PaymentEntry $entry, bool $inHook): void
    {
        $amount = $this->getAmount($entry, $inHook);

        $allocatedAmount = Currency::create('0', $amount->getCode());

        foreach ($entry->getAllocations() as $allocation) {
            $allocatedAmount = $allocatedAmount->add($allocation->getAmount());
        }

        if ($entry instanceof CreditNote || $entry instanceof SupplierCredit) {
            foreach ($this->getAllocations($entry) as $allocation) {
                $allocatedAmount = $allocatedAmount->add($allocation->getAmount());
            }
        }

        if ($allocatedAmount->compare($amount) > 0) {
            if ($entry instanceof CreditNote || $entry instanceof SupplierCredit) {
                $this->throwForbidden('amountDueMustNotBeLessThanZero', CreditNote::ENTITY_TYPE);
            }

            $this->throwForbidden('unallocatedAmountMustNotBeLessThanZero');
        }

        if (!$this->isCompleted($entry)) {
            return;
        }

        if ($allocatedAmount->compare($amount) !== 0) {
            if ($entry instanceof CreditNote || $entry instanceof SupplierCredit) {
                $this->throwForbidden('cannotCompleteWithAmountDue', CreditNote::ENTITY_TYPE);
            }

            $this->throwForbidden('unallocatedAmountMustBeZero');
        }
    }

    private function getAmount(SupplierCredit|WriteOffEntry|CreditNote|PaymentEntry $entry, bool $inHook): Currency
    {
        if ($entry instanceof PaymentEntry || $entry instanceof WriteOffEntry) {
            return $entry->getAmount();
        }

        if (!$inHook) {
            /** @var CreditNote|SupplierCredit $dummy */
            $dummy = $this->entityManager->getRDBRepositoryByClass($entry::class)->getNew();
            $dummy->setMultiple($entry->getValueMap());
            $entry = $dummy;

            $this->beforeSaveProcessor->process($entry);
        }

        $amount = $entry->getGrandTotalAmount();


        if (!$amount) {
            // Does not suppose to happen.
            throw new RuntimeException("No grand total amount.");
        }

        return $amount;
    }

    /**
     * @throws BadRequest
     */
    private function throwBadRequest(string $key): never
    {
        throw BadRequest::createWithBody(
            $key,
            Body::create()->withMessageTranslation($key, PaymentEntry::ENTITY_TYPE)
        );
    }

    /**
     * @throws Forbidden
     */
    private function throwForbidden(string $key, ?string $scope = null): never
    {
        throw Forbidden::createWithBody(
            $key,
            Body::create()->withMessageTranslation($key, $scope ?? PaymentEntry::ENTITY_TYPE)
        );
    }

    /**
     * @return PaymentAllocation[]
     */
    private function getAllocations(SupplierCredit|CreditNote $entry): array
    {
        if ($entry->isNew()) {
            return [];
        }

        $allocations = $this->entityManager
            ->getRDBRepositoryByClass(PaymentAllocation::class)
            ->where([
                PaymentAllocation::LINK_TARGET . 'Id' => $entry->getId(),
                PaymentAllocation::LINK_TARGET . 'Type' => $entry->getEntityType(),
            ])
            ->find();

        return iterator_to_array($allocations);
    }

    private function isCompleted(SupplierCredit|WriteOffEntry|CreditNote|PaymentEntry $entry): bool
    {
        if ($entry instanceof CreditNote && $entry->getStatus() !== CreditNote::STATUS_RESOLVED) {
            return false;
        }

        if ($entry instanceof SupplierCredit && $entry->getStatus() !== SupplierCredit::STATUS_RESOLVED) {
            return false;
        }

        if ($entry instanceof WriteOffEntry && $entry->getStatus() !== WriteOffEntry::STATUS_APPLIED) {
            return false;
        }

        if ($entry instanceof PaymentEntry && $entry->getStatus() !== PaymentEntry::STATUS_COMPLETED) {
            return false;
        }

        return true;
    }
}
