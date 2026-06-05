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

namespace Espo\Modules\Sales\Tools\Quote;

use Espo\Modules\Crm\Entities\Opportunity;
use Espo\Modules\Sales\Entities\TaxAllocationItem;
use Espo\Modules\Sales\Entities\TaxLineItem;
use Espo\Modules\Sales\Entities\TaxTotalItem;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\Modules\Sales\Tools\Sales\OrderEntityUtil;
use Espo\ORM\EntityManager;

class ItemsRemoveProcessor
{
    public function __construct(
        private EntityManager $entityManager,
    ) {}

    public function process(OrderEntity|Opportunity $order): void
    {
        $this->removeItems($order);
        $this->removeTaxLineItems($order);
        $this->removeTaxTotalItems($order);
        $this->removeTaxAllocationItems($order);
    }

    private function removeItems(OrderEntity|Opportunity $order): void
    {
        $itemEntityType = OrderEntityUtil::getItemEntityType($order->getEntityType());
        $itemParentIdAttribute = lcfirst($order->getEntityType()) . 'Id';

        $quoteItemList = $this->entityManager
            ->getRDBRepository($itemEntityType)
            ->where([$itemParentIdAttribute => $order->getId()])
            ->find();

        foreach ($quoteItemList as $item) {
            $this->entityManager->removeEntity($item);
        }
    }

    private function removeTaxLineItems(OrderEntity|Opportunity $order): void
    {
        if (
            !$order instanceof OrderEntity ||
            !OrderEntityUtil::isWithTax($order->getEntityType())
        ) {
            return;
        }

        $items = $this->entityManager
            ->getRDBRepositoryByClass(TaxLineItem::class)
            ->where([
                TaxLineItem::FIELD_SOURCE . 'Id' => $order->getId(),
                TaxLineItem::FIELD_SOURCE . 'Type' => $order->getEntityType(),
            ])
            ->order(TaxLineItem::FIELD_ORDER)
            ->find();

        foreach ($items as $item) {
            $this->entityManager->removeEntity($item);
        }
    }

    private function removeTaxTotalItems(OrderEntity|Opportunity $order): void
    {
        if (
            !$order instanceof OrderEntity ||
            !OrderEntityUtil::isWithTax($order->getEntityType())
        ) {
            return;
        }

        $items = $this->entityManager
            ->getRDBRepositoryByClass(TaxTotalItem::class)
            ->where([
                TaxTotalItem::FIELD_SOURCE . 'Id' => $order->getId(),
                TaxTotalItem::FIELD_SOURCE . 'Type' => $order->getEntityType(),
            ])
            ->order(TaxTotalItem::FIELD_ORDER)
            ->find();

        foreach ($items as $item) {
            $this->entityManager->removeEntity($item);
        }
    }

    private function removeTaxAllocationItems(OrderEntity|Opportunity $order): void
    {
        if (
            !$order instanceof OrderEntity ||
            !OrderEntityUtil::isWithTaxCashBasis($order->getEntityType())
        ) {
            return;
        }

        $items = $this->entityManager
            ->getRDBRepositoryByClass(TaxAllocationItem::class)
            ->where([
                TaxAllocationItem::FIELD_SOURCE . 'Id' => $order->getId(),
                TaxAllocationItem::FIELD_SOURCE . 'Type' => $order->getEntityType(),
            ])
            ->order(TaxAllocationItem::FIELD_ORDER)
            ->find();

        foreach ($items as $item) {
            $this->entityManager->removeEntity($item);
        }
    }
}
