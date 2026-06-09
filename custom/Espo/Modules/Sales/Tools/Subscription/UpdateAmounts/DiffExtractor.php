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

namespace Espo\Modules\Sales\Tools\Subscription\UpdateAmounts;

use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Tools\Invoice\InvoiceOrderItem;

class DiffExtractor
{
    public function extract(Invoice $credit, Invoice $charge): ?Diff
    {
        if ($credit->getAmountCurrency() !== $charge->getAmountCurrency()) {
            return null;
        }

        $creditItems = $credit->getItems();
        $chargeItems = $charge->getItems();

        $maxCount = max(count($creditItems), count($chargeItems));

        for ($i = 0; $i < $maxCount; $i++) {
            $it1 = $creditItems[$i] ?? null;
            $it2 = $chargeItems[$i] ?? null;

            if (!$it1 || !$it2) {
                break;
            }

            if ($it1->getProductId() !== $it2->getProductId()) {
                return null;
            }

            if ($it1->getUnitPrice()?->getAmount() !== $it2->getUnitPrice()?->getAmount()) {
                return null;
            }
        }

        $items = $this->tryExtract($creditItems, $chargeItems);

        if ($items) {
            return new Diff(
                items: $items,
                isCredit: true,
            );
        }

        $items = $this->tryExtract($chargeItems, $creditItems);

        if ($items) {
            return new Diff(
                items: $items,
                isCredit: false,
            );
        }

        return null;
    }

    /**
     * @param InvoiceOrderItem[] $items
     * @param InvoiceOrderItem[] $otherItems
     * @return ?InvoiceOrderItem[]
     */
    private function tryExtract(array $items, array $otherItems): ?array
    {
        $output = [];

        if (count($items) < count($otherItems)) {
            return null;
        }

        for ($i = 0; $i < count($items); $i++) {
            $item = $items[$i];
            $other = $otherItems[$i] ?? null;

            if (!$other) {
                $output[] = $item;

                continue;
            }

            if ($item->getQuantity() < $other->getQuantity()) {
                return null;
            }

            if ($item->getQuantity() === $other->getQuantity()) {
                continue;
            }

            if (!$item->getUnitPrice()) {
                continue;
            }

            $diffQuantity = $item->getQuantity() - $other->getQuantity();

            $newItem = $item
                ->withQuantity($diffQuantity)
                ->withAmount($item->getUnitPrice()->multiply($diffQuantity));

            $output[] = $newItem;
        }

        return $output ?: null;
    }
}
