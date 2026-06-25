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

namespace Espo\Modules\Sales\Tools\Invoice;

use Espo\Core\Field\Currency;
use Espo\Modules\Sales\Entities\CreditNote;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Entities\Tax;
use Espo\Modules\Sales\Tools\Quote\ShippingCostBreakdownItem;

/**
 * Legacy? To remove in future?
 */
class ShippingCostBreakdownUtil
{
    /**
     * @return ShippingCostBreakdownItem[]
     */
    public static function breakdown(Invoice|CreditNote $order): array
    {
        $cost = $order->getShippingCost();

        if (!$cost || $cost->getAmount() === 0.0) {
            return [];
        }

        if (!$order->getShippingTaxMode()) {
            return [
                new ShippingCostBreakdownItem(
                    amount: $cost,
                    taxRate: 0.0,
                )
            ];
        }

        if ($order->getShippingTaxMode() === Tax::SHIPPING_MODE_FIXED) {
            return [
                new ShippingCostBreakdownItem(
                    amount: $cost,
                    taxRate: $order->getTaxRate() ?? 0.0,
                )
            ];
        }

        if ($order->getShippingTaxMode() === Tax::SHIPPING_MODE_PROPORTIONAL) {
            if (!$order->hasItemList()) {
                $order->loadItemListField();
            }

            $amount = $order->getAmount();

            if (!$amount) {
                return [
                    new ShippingCostBreakdownItem(
                        amount: $cost,
                        taxRate: 0.0,
                    )
                ];
            }

            /** @var ShippingCostBreakdownItem[] $output */
            $output = [];

            foreach ($order->getItems() as $item) {
                $itemAmount = (float) $item->get('amount');
                $itemRate = (float) $item->get('taxRate');

                if (!$itemAmount) {
                    continue;
                }

                $portionAmount = $cost->getAmount() * $itemAmount / $amount->getAmount();
                $portionAmount = round($portionAmount, 2);

                $newItem = new ShippingCostBreakdownItem(
                    amount: Currency::create($portionAmount, $amount->getCode()),
                    taxRate: $itemRate,
                );

                foreach ($output as $i => $sItem) {
                    if ($sItem->getTaxRate() === $newItem->getTaxRate()) {
                        $output[$i] = new ShippingCostBreakdownItem(
                            amount: $sItem->getAmount()->add($newItem->getAmount()),
                            taxRate: $sItem->getTaxRate(),
                        );

                        continue 2;
                    }
                }

                $output[] = $newItem;
            }

            if (!count($output)) {
                return [
                    new ShippingCostBreakdownItem(
                        amount: $cost,
                        taxRate: 0.0,
                    )
                ];
            }

            usort($output, function (ShippingCostBreakdownItem $a, ShippingCostBreakdownItem $b) {
                return $a->getTaxRate() <=> $b->getTaxRate();
            });

            return $output;
        }

        return [];
    }
}
