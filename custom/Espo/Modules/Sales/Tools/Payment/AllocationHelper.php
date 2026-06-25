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

namespace Espo\Modules\Sales\Tools\Payment;

use Espo\Modules\Sales\Entities\CreditNote;
use Espo\Modules\Sales\Entities\PaymentEntry;
use Espo\Modules\Sales\Entities\SupplierCredit;
use Espo\Modules\Sales\Entities\WriteOffEntry;

class AllocationHelper
{
    /**
     * @return Allocation[]
     */
    public static function getRemovedAllocations(PaymentEntry|WriteOffEntry|CreditNote|SupplierCredit $entry): array
    {
        $removedAllocations = [];

        foreach ($entry->getFetchedAllocations() as $fetchedAllocation) {
            foreach ($entry->getAllocations() as $allocation) {
                if (self::areSameTargets($allocation, $fetchedAllocation)) {
                    continue 2;
                }
            }

            $removedAllocations[] = $fetchedAllocation;
        }

        return $removedAllocations;
    }

    public static function isAllocationChangedOrAdded(
        PaymentEntry|WriteOffEntry|CreditNote|SupplierCredit $entry,
        Allocation $allocation,
    ): bool {

        foreach ($entry->getFetchedAllocations() as $fetchedAllocation) {
            if (self::areSameTargets($allocation, $fetchedAllocation)) {
                if (
                    $allocation->getAmount()->compare($fetchedAllocation->getAmount()) === 0
                ) {
                    return false;
                }

                return true;
            }
        }

        return true;
    }

    private static function areSameTargets(Allocation $allocation, Allocation $fetchedAllocation): bool
    {
        return $allocation->getTarget()->getEntityType() === $fetchedAllocation->getTarget()->getEntityType() &&
            $allocation->getTarget()->getId() === $fetchedAllocation->getTarget()->getId();
    }
}
