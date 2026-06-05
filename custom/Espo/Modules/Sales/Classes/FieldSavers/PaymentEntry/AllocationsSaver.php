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

namespace Espo\Modules\Sales\Classes\FieldSavers\PaymentEntry;

use Espo\Core\Field\Currency;
use Espo\Core\Field\Date;
use Espo\Core\Field\LinkParent;
use Espo\Core\FieldProcessing\Saver;
use Espo\Core\FieldProcessing\Saver\Params;
use Espo\Core\Utils\DateTime;
use Espo\Core\WebSocket\Submission;
use Espo\Modules\Sales\Entities\CreditNote;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Entities\PaymentAllocation;
use Espo\Modules\Sales\Entities\PaymentEntry;
use Espo\Modules\Sales\Entities\SupplierBill;
use Espo\Modules\Sales\Entities\SupplierCredit;
use Espo\Modules\Sales\Entities\WriteOffEntry;
use Espo\Modules\Sales\Tools\Payment\Allocation;
use Espo\Modules\Sales\Tools\PaymentTerms\PaymentInstallmentsStatusUpdater;
use Espo\Modules\Sales\Tools\Quote\CurrencyConverterUtil;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\Modules\Sales\Tools\Sales\OrderEntityUtil;
use Espo\Modules\Sales\Tools\Tax\TaxAllocationProcessor;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * @implements Saver<PaymentEntry|CreditNote|WriteOffEntry|SupplierCredit>
 */
class AllocationsSaver implements Saver
{
    private const ATTR_ALLOCATIONS = 'allocations';
    private const ATTR_AMOUNT = 'amount';

    public function __construct(
        private EntityManager $entityManager,
        private CurrencyConverterUtil $currencyConverterUtil,
        private DateTime $dateTime,
        private PaymentInstallmentsStatusUpdater $installmentsStatusUpdater,
        private TaxAllocationProcessor $taxAllocationProcessor,
        private Submission $submission,
    ) {}

    public function process(Entity $entity, Params $params): void
    {
        if (
            !$entity->isAttributeChanged(self::ATTR_ALLOCATIONS) &&
            !$entity->isAttributeChanged(self::ATTR_AMOUNT) &&
            !$entity->isAttributeChanged(OrderEntity::FIELD_CURRENCY_RATE)
        ) {
            return;
        }

        if (!$entity->isNew()) {
            $this->processNotNew($entity);

            return;
        }

        $this->createItems($entity, $entity->getAllocations());
    }

    /**
     * @return PaymentAllocation[]
     */
    private function getStoredAllocations(PaymentEntry|CreditNote|WriteOffEntry|SupplierCredit $entity): array
    {
        if ($entity instanceof PaymentEntry) {
            $idAtt = PaymentAllocation::ATTR_PAYMENT_ENTRY_ID;
        } else if ($entity instanceof CreditNote) {
            $idAtt = PaymentAllocation::ATTR_CREDIT_NOTE_ID;
        } else if ($entity instanceof SupplierCredit) {
            $idAtt = PaymentAllocation::ATTR_SUPPLIER_CREDIT_ID;
        } else {
            $idAtt = PaymentAllocation::ATTR_WRITE_OFF_ENTRY_ID;
        }

        $storedAllocations = $this->entityManager
            ->getRDBRepositoryByClass(PaymentAllocation::class)
            ->where([$idAtt => $entity->getId()])
            ->find();

        return [...$storedAllocations];
    }

    private function areSame(PaymentAllocation $allocation, Allocation $item): bool
    {
        return $allocation->getTarget() &&
            $item->getTarget()->getId() === $allocation->getTarget()->getId() &&
            $item->getTarget()->getEntityType() === $allocation->getTarget()->getEntityType();
    }

    /**
     * @param Allocation[] $newItems
     */
    private function createItems(
        PaymentEntry|CreditNote|WriteOffEntry|SupplierCredit $entity,
        array $newItems,
        ?Date $date = null,
    ): void {

        $date ??= $this->dateTime->getToday();

        foreach ($newItems as $item) {
            $allocation = $this->entityManager->getRDBRepositoryByClass(PaymentAllocation::class)->getNew();

            $allocation->setTarget($item->getTarget());

            $this->setAmount($allocation, $item->getAmount(), $entity);

            if ($entity instanceof CreditNote) {
                $allocation->setCreditNote($entity);
            } else if ($entity instanceof WriteOffEntry) {
                $allocation->setWriteOffEntry($entity);
            } else if ($entity instanceof SupplierCredit) {
                $allocation->setSupplierCredit($entity);
            } else {
                $allocation->setPaymentEntry($entity);
            }

            $allocation->setDate($date);

            $this->entityManager->saveEntity($allocation);

            $this->afterSaveOrRemove($allocation);
        }
    }

    private function processNotNew(PaymentEntry|CreditNote|WriteOffEntry|SupplierCredit $entity): void
    {
        $date = $this->dateTime->getToday();

        $updateList = [];
        $deleteList = [];

        $items = $entity->getAllocations();

        foreach ($this->getStoredAllocations($entity) as $allocation) {
            $found = false;

            foreach ($items as $item) {
                if ($this->areSame($allocation, $item)) {
                    $this->setAmount($allocation, $item->getAmount(), $entity);

                    $updateList[] = $allocation;
                    $found = true;

                    break;
                }
            }

            if (!$found) {
                $deleteList[] = $allocation;
            }
        }

        $newItems = [];

        foreach ($items as $item) {
            foreach ($updateList as $allocation) {
                if ($this->areSame($allocation, $item)) {
                    continue 2;
                }
            }

            $newItems[] = $item;
        }

        foreach ($deleteList as $allocation) {
            $this->entityManager->removeEntity($allocation);

            $this->afterSaveOrRemove($allocation, true);
        }

        foreach ($updateList as $allocation) {
            if ($allocation->isAnyAmountChanged()) {
                $allocation->setDate($date);
            }

            $this->entityManager->saveEntity($allocation);

            $this->afterSaveOrRemove($allocation);
        }

        $this->createItems($entity, $newItems, $date);
    }

    private function setAmount(
        PaymentAllocation $allocation,
        Currency $amount,
        PaymentEntry|CreditNote|WriteOffEntry|SupplierCredit $entity,
    ): void {

        $allocation->setAmount($amount);

        if (
            $entity instanceof CreditNote ||
            $entity instanceof PaymentEntry ||
            $entity instanceof SupplierCredit
        ) {
            $amountLocal = $this->currencyConverterUtil->convertToLocal($amount, $entity);
            $fxGainLoss = $this->calculateFxGainLoss($allocation, $entity, $amountLocal);

            $allocation
                ->setAmountLocal($amountLocal)
                ->setFxGainLoss($fxGainLoss);
        }

        if ($entity instanceof WriteOffEntry) {
            $amountLocal = null;
            $fxGainLoss = null;

            $invoice = $allocation->getTarget();

            if ($invoice instanceof Invoice) {
                $amountLocal = $this->currencyConverterUtil->convertToLocal($amount, $invoice);
                $fxGainLoss = Currency::create('0', $amountLocal->getCode());
            }

            $allocation
                ->setAmountLocal($amountLocal)
                ->setFxGainLoss($fxGainLoss);
        }
    }

    private function calculateFxGainLoss(
        PaymentAllocation $allocation,
        CreditNote|PaymentEntry|SupplierCredit $entity,
        Currency $amountLocal,
    ): Currency {

        $target = $allocation->getTarget();

        $rate = $entity->getCurrencyRate();
        $originalRate = $target?->getCurrencyRate();
        $amount = $allocation->getAmount();
        $localCode = $amountLocal->getCode();

        if ($originalRate === null || $rate === null) {
            return Currency::create('0', $amountLocal->getCode());
        }

        $amountOriginalLocal = $this->currencyConverterUtil->convert($amount, $localCode, $originalRate);

        $isReverse = false;

        if ($target instanceof CreditNote || $target instanceof SupplierBill) {
            $isReverse = true;
        }

        return $isReverse ?
            $amountOriginalLocal->subtract($amountLocal) :
            $amountLocal->subtract($amountOriginalLocal);
    }

    private function afterSaveOrRemove(PaymentAllocation $allocation, bool $isRemove = false): void
    {
        $target = $allocation->getTargetLink();

        if (!$target) {
            return;
        }

        if (OrderEntityUtil::isWithTaxCashBasis($target->getEntityType())) {
            $this->afterSaveOrRemoveProcessTaxAllocations($allocation, $isRemove);
        }

        if ($target->getEntityType() !== Invoice::ENTITY_TYPE) {
            $this->submitRecordUpdate($target);

            return;
        }

        if ($isRemove) {
            $invoice = $this->entityManager->getRDBRepositoryByClass(Invoice::class)->getById($target->getId());
        } else {
            $invoice = $allocation->getTarget();
        }

        if (!$invoice instanceof Invoice) {
            return;
        }

        $this->installmentsStatusUpdater->update($invoice);

        $this->submitRecordUpdate($target);
    }

    private function afterSaveOrRemoveProcessTaxAllocations(PaymentAllocation $allocation, bool $isRemove): void
    {
        if (!$allocation->get(PaymentAllocation::ATTR_PAYMENT_ENTRY_ID)) {
            return;
        }

        if ($isRemove) {
            $this->taxAllocationProcessor->processRemove($allocation);

            return;
        }

        $this->taxAllocationProcessor->processSave($allocation);
    }


    private function submitRecordUpdate(LinkParent $target): void
    {
        $this->submission->submit("recordUpdate.{$target->getEntityType()}.{$target->getId()}");
    }
}
