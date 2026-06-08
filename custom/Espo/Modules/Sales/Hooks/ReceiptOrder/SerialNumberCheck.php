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

namespace Espo\Modules\Sales\Hooks\ReceiptOrder;

use Espo\Core\Currency\CalculatorUtil;
use Espo\Modules\Sales\Entities\InventoryNumber;
use Espo\Modules\Sales\Entities\InventoryTransaction;
use Espo\Modules\Sales\Entities\ReceiptOrder;
use Espo\ORM\EntityManager;
use Espo\ORM\Name\Attribute;
use Espo\ORM\Query\Part\Expression as Expr;
use Espo\ORM\Query\SelectBuilder;
use RuntimeException;

class SerialNumberCheck
{
    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function check(ReceiptOrder $order): bool
    {
        $numberMap = $this->getInventoryNumberMap($order);

        if (!$this->checkSerialNumberQuantityInternal($order, $numberMap)) {
            return false;
        }

        return $this->findInStockInternal($order, $numberMap) === [];
    }

    /**
     * @return InventoryNumber[]
     */
    public function findInStock(ReceiptOrder $order): array
    {
        $numberMap = $this->getInventoryNumberMap($order);

        return $this->findInStockInternal($order, $numberMap);
    }

    /**
     * @param array<string, InventoryNumber> $numberMap
     * @return InventoryNumber[]
     */
    private function findInStockInternal(ReceiptOrder $order, array $numberMap): array
    {
        $numberIds = [];

        foreach ($order->getReceivedItems() as $item) {
            $number = $numberMap[$item->getInventoryNumberId()] ?? null;

            if (
                !$number ||
                $number->getType() !== InventoryNumber::TYPE_SERIAL
            ) {
                continue;
            }

            $numberIds[] = $number->getId();
        }

        $builder = SelectBuilder::create()
            ->from(InventoryTransaction::ENTITY_TYPE)
            ->select('inventoryNumberId')
            ->select(
                Expr::sum(Expr::column('quantity')),
                'sum'
            )
            ->where([
                'inventoryNumberId' => $numberIds,
            ])
            ->group('inventoryNumberId');

        if ($order->hasId()) {
            $builder->where([
                'OR' => [
                    'parentType!=' => ReceiptOrder::ENTITY_TYPE,
                    'parentId!=' => $order->getId(),
                    'parentId' => null,
                ]
            ]);
        }

        $query = $builder->build();

        $sth = $this->entityManager->getQueryExecutor()->execute($query);

        $inStockIds = [];

        while ($row = $sth->fetch()) {
            /** @var numeric-string $quantity */
            $quantity = (string) ($row['sum'] ?? '0.0');

            $id = $row['inventoryNumberId'];

            if (CalculatorUtil::compare($quantity, '0') > 0) {
                $inStockIds[] = $id;
            }
        }

        if ($inStockIds === []) {
            return [];
        }

        $numbers = $this->entityManager
            ->getRDBRepositoryByClass(InventoryNumber::class)
            ->where([Attribute::ID => $inStockIds])
            ->find();

        return iterator_to_array($numbers);
    }

    /**
     * @return array<string, InventoryNumber>
     */
    private function getInventoryNumberMap(ReceiptOrder $order): array
    {
        $numberIds = [];

        foreach ($order->getReceivedItems() as $item) {
            $numberIds[] = $item->getInventoryNumberId();
        }

        /** @var iterable<InventoryNumber> $numbers */
        $numbers = $this->entityManager
            ->getRDBRepositoryByClass(InventoryNumber::class)
            ->where(['id' => $numberIds])
            ->find();

        $map = [];

        foreach ($numbers as $number) {
            $map[$number->getId()] = $number;
        }

        return $map;
    }

    /**
     * @param array<string, InventoryNumber> $numberMap
     */
    private function checkSerialNumberQuantityInternal(ReceiptOrder $order, array $numberMap): bool
    {
        foreach ($order->getReceivedItems() as $item) {
            if (!$item->getInventoryNumberId()) {
                continue;
            }

            $number = $numberMap[$item->getInventoryNumberId()] ?? null;

            if (!$number) {
                throw new RuntimeException("No inventory number.");
            }

            if ($number->getType() !== InventoryNumber::TYPE_SERIAL) {
                continue;
            }

            if ($item->getQuantity() !== 1.0 && $item->getQuantity() !== 0.0) {
                return false;
            }
        }

        return true;
    }
}
