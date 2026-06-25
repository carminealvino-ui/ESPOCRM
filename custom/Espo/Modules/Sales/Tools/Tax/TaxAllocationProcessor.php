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

namespace Espo\Modules\Sales\Tools\Tax;

use Espo\Core\Currency\CalculatorUtil;
use Espo\Core\Field\Currency;
use Espo\Core\Field\LinkParent;
use Espo\Modules\Sales\Entities\CreditNote;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Entities\PaymentAllocation;
use Espo\Modules\Sales\Entities\PaymentEntry;
use Espo\Modules\Sales\Entities\SupplierBill;
use Espo\Modules\Sales\Entities\SupplierCredit;
use Espo\Modules\Sales\Entities\TaxAllocationItem;
use Espo\Modules\Sales\Entities\TaxLineItem;
use Espo\Modules\Sales\Tools\Quote\CurrencyConverterUtil;
use Espo\Modules\Sales\Tools\Quote\RoundingUtil;
use Espo\Modules\Sales\Tools\Sales\OrderEntityUtil;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Name\Attribute;
use Espo\ORM\Query\DeleteBuilder;
use RuntimeException;

class TaxAllocationProcessor
{
    private const PORTION_PRECISION = 6;

    public function __construct(
        private EntityManager $entityManager,
        private RoundingUtil $roundingUtil,
        private CurrencyConverterUtil $currencyConverterUtil,
    ) {}

    public function processSave(PaymentAllocation $allocation): void
    {
        if (!$allocation->getTargetLink()) {
            return;
        }

        $this->entityManager->getTransactionManager()->run(function () use ($allocation) {
            $target = $this->getTargetAndLock($allocation->getTargetLink());

            $this->processSaveInternal($allocation, $target);
        });
    }

    /**
     * The allocation is already deleted. Do not obtain relations from the entity.
     */
    public function processRemove(PaymentAllocation $allocation): void
    {
        if (!$allocation->getTargetLink()) {
            return;
        }

        $this->entityManager->getTransactionManager()->run(function () use ($allocation) {
            $this->getTargetAndLock($allocation->getTargetLink());

            $this->processRemoveInternal($allocation);
        });
    }

    private function getTargetAndLock(LinkParent $link): ?Entity
    {
        $this->entityManager
            ->getRDBRepository($link->getEntityType())
            ->select(Attribute::ID)
            ->forUpdate()
            ->where([Attribute::ID => $link->getId()])
            ->findOne();

        return $this->entityManager
            ->getRDBRepository($link->getEntityType())
            ->where([Attribute::ID => $link->getId()])
            ->findOne();
    }

    private function processSaveInternal(PaymentAllocation $allocation, ?Entity $target): void
    {
        $this->deleteItems($allocation);

        if (
            !$target ||
            !OrderEntityUtil::isWithTaxCashBasis($target->getEntityType()) ||
            !$target instanceof Invoice &&
            !$target instanceof CreditNote &&
            !$target instanceof SupplierBill &&
            !$target instanceof SupplierCredit
        ) {
            return;
        }

        $entry = $allocation->getPaymentEntry();

        if (!$entry) {
            return;
        }

        $total = $target->getGrandTotalAmount() ?? throw new RuntimeException("No grand total.");
        $rate = $entry->getCurrencyRate();
        //$due = $target->getAmountDue() ?? throw new RuntimeException("No amount due.");

        if (!$rate || CalculatorUtil::compare($total->getAmountAsString(), '0') === 0) {
            return;
        }

        $portion = $this->calculatePortion($allocation, $total);

        //$isLast = CalculatorUtil::compare($due->getAmountAsString(), '0') === 0;

        // @todo Test.
        /*if (
            $isLast &&
            CalculatorUtil::compare($portion, '100') !== 0 &&
            $this->isOnlyPaymentsAllocated($target)
        ) {
            $previousTaxAllocationItems = $this->getPreviousTaxAllocationItems($target);

            foreach ($target->getTaxTotals() as $i => $totalItem) {
                $prevItems = $this->filterItems($previousTaxAllocationItems, $totalItem);
                $restTotalItem = $this->calculateRestTotalItem($entry, $totalItem, $prevItems);

                $this->createAllocationItem(
                    totalItem: $totalItem,
                    allocation: $allocation,
                    target: $target,
                    entry: $entry,
                    portion: $portion,
                    i: $i,
                    restTotalItem: $restTotalItem,
                );
            }

            return;
        }*/

        foreach ($target->getTaxLineItemCollection() as $i => $item) {
            $this->createAllocationItem(
                lineItem: $item,
                allocation: $allocation,
                target: $target,
                entry: $entry,
                portion: $portion,
                i: $i,
            );
        }
    }

    private function processRemoveInternal(PaymentAllocation $allocation): void
    {
        $this->deleteItems($allocation);
    }

    private function deleteItems(PaymentAllocation $allocation): void
    {
        $query = DeleteBuilder::create()
            ->from(TaxAllocationItem::ENTITY_TYPE)
            ->where([
                TaxAllocationItem::FIELD_ALLOCATION . 'Id' => $allocation->getId(),
            ])
            ->build();

        $this->entityManager->getQueryExecutor()->execute($query);
    }

    /**
     * @return numeric-string
     */
    private function calculatePortion(PaymentAllocation $allocation, Currency $total): string
    {
        $portion =
            CalculatorUtil::divide(
                $allocation->getAmount()->getAmountAsString(),
                $total->getAmountAsString()
            );

        return CalculatorUtil::round($portion, self::PORTION_PRECISION);
    }

    /**
     * @param numeric-string $portion
     */
    private function createAllocationItem(
        TaxLineItem $lineItem,
        PaymentAllocation $allocation,
        Invoice|CreditNote|SupplierBill|SupplierCredit $target,
        PaymentEntry $entry,
        string $portion,
        int $i,
        //?TaxTotalLine $restTotalItem = null,
    ): void {

        $item = $this->entityManager->getRDBRepositoryByClass(TaxAllocationItem::class)->getNew();

        $percentage = CalculatorUtil::multiply($portion, '100');
        $isFullPortion = CalculatorUtil::compare($portion, '1') === 0;

        $taxCode = $lineItem->getTaxCode();
        $rate = $lineItem->getRate();

        /*if ($restTotalItem) {
            $amount = $restTotalItem->amount;
            $amountLocal = $restTotalItem->amountLocal;
            $baseAmount = $restTotalItem->baseAmount;
            $baseAmountLocal = $restTotalItem->baseAmountLocal;
        } else {}*/

        $baseAmount = $lineItem->getBaseAmount()->multiply($portion);
        $baseAmount = $this->roundingUtil->round($baseAmount);

        if (
            !$isFullPortion &&
            $rate !== null &&
            $taxCode->getRoundingLevel() === TaxRoundingLevel::Line
        ) {
            $amount = $baseAmount->multiply($rate)->divide('100');
            $amount = $this->roundingUtil->round($amount/*, $taxCode->getRoundingFactor()*/);
        } else {
            $amount = $lineItem->getAmount()->multiply($portion);
            $amount = $this->roundingUtil->round($amount);
        }

        $baseAmountLocal = $this->currencyConverterUtil->convertToLocal($baseAmount, $entry);
        $amountLocal = $this->currencyConverterUtil->convertToLocal($amount, $entry);

        $item
            ->setOrder($i)
            ->setProduct($lineItem->getProductLink())
            ->setItem($lineItem->getItemLink())
            ->setComponent($lineItem->getComponent())
            ->setPaymentEntry($entry)
            ->setAllocation($allocation)
            ->setPercentage($percentage)
            ->setTaxCode($lineItem->getTaxCode())
            ->setRate($lineItem->getRate())
            ->setSource($target)
            ->setAmount($amount)
            ->setAmountLocal($amountLocal)
            ->setBaseAmount($baseAmount->getAmountAsString())
            ->setBaseAmountLocal($baseAmountLocal->getAmountAsString());

        $this->entityManager->saveEntity($item);
    }

    /*
     * @return TaxAllocationItem[]
     */
    /*private function getPreviousTaxAllocationItems(
        SupplierBill|SupplierCredit|CreditNote|Invoice $target,
    ): array {

        $items = $this->entityManager
            ->getRDBRepositoryByClass(TaxAllocationItem::class)
            ->where([
                TaxAllocationItem::ATTR_SOURCE_ID => $target->getId(),
                TaxAllocationItem::ATTR_SOURCE_TYPE => $target->getEntityType(),
            ])
            ->find();

        return iterator_to_array($items);
    }*/

    /*
     * @param TaxAllocationItem[] $previousItems
     */
    /*private function calculateRestTotalItem(
        PaymentEntry $entry,
        TaxTotalLine $totalItem,
        array $previousItems,
    ): TaxTotalLine {

        $baseAmount = $totalItem->baseAmount;
        $amount = $totalItem->amount;

        foreach ($previousItems as $item) {
            $baseAmount = $baseAmount->subtract($item->getBaseAmount());
            $amount = $amount->subtract($item->getAmount());
        }

        $rate = $totalItem->taxCode->getRate();

        if ($rate !== null) {
            $amount = $baseAmount->multiply($rate)->divide('100');
            $amount = $this->roundingUtil->round($amount);
        }

        $baseAmountLocal = $this->currencyConverterUtil->convertToLocal($baseAmount, $entry);
        $amountLocal = $this->currencyConverterUtil->convertToLocal($amount, $entry);

        return new TaxTotalLine(
            taxCode: $totalItem->taxCode,
            amount: $amount,
            baseAmount: $baseAmount,
            amountLocal: $amountLocal,
            baseAmountLocal: $baseAmountLocal,
        );
    }*/

    /*
     * @param TaxAllocationItem[] $previousTaxAllocationItems
     * @return TaxAllocationItem[]
     */
    /*private function filterItems(array $previousTaxAllocationItems, TaxTotalLine $totalItem): array
    {
        $prevItems = array_filter($previousTaxAllocationItems, function ($it) use ($totalItem) {
            return $it->getTaxCode()->getId() === $totalItem->taxCode->getId();
        });

        return array_values($prevItems);
    }*/

    /*private function isOnlyPaymentsAllocated(Entity $target): bool
    {
        $one = $this->entityManager
            ->getRDBRepositoryByClass(PaymentAllocation::class)
            ->select([Attribute::ID])
            ->where([
                PaymentAllocation::ATTR_PAYMENT_ENTRY_ID => null,
                PaymentAllocation::LINK_TARGET . 'Id' => $target->getId(),
                PaymentAllocation::LINK_TARGET . 'Type' => $target->getEntityType(),
            ])
            ->findOne();

        return $one === null;
    }*/
}
