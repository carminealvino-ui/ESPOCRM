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

namespace Espo\Modules\Sales\Tools\PaymentTerms;

use Espo\Modules\Sales\Entities\PaymentInstallment;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\ORM\EntityManager;
use Espo\ORM\Query\DeleteBuilder;
use LogicException;

class PaymentInstallmentsSaveProcessor
{
    public function __construct(
        private EntityManager $entityManager,
    ) {}

    public function process(OrderEntity & PaymentTermsHavingOrder $order): void
    {
        $saveItems = $order->getInstallmentSaveItems();

        if ($saveItems === null) {
            return;
        }

        $currentTerms = $this->getCurrentPaymentTerms($order);

        if (!$this->areDifferent($currentTerms, $saveItems)) {
            if (!$order->isNew()) {
                $this->updateStatuses($currentTerms, $saveItems);
            }

            return;
        }

        $this->populate($order, $saveItems);
    }

    private function deleteItems(OrderEntity & PaymentTermsHavingOrder $order): void
    {
        $query = DeleteBuilder::create()
            ->from(PaymentInstallment::ENTITY_TYPE)
            ->where([
                PaymentInstallment::FIELD_SOURCE . 'Type' => $order->getEntityType(),
                PaymentInstallment::FIELD_SOURCE . 'Id' => $order->getId(),
            ])
            ->build();

        $this->entityManager->getQueryExecutor()->execute($query);
    }

    /**
     * @return PaymentInstallment[]
     */
    private function getCurrentPaymentTerms(OrderEntity & PaymentTermsHavingOrder $order): array
    {
        if ($order->isNew()) {
            return [];
        }

        $collection = $this->entityManager
            ->getRDBRepositoryByClass(PaymentInstallment::class)
            ->where([
                PaymentInstallment::FIELD_SOURCE . 'Id' => $order->getId(),
                PaymentInstallment::FIELD_SOURCE . 'Type' => $order->getEntityType(),
            ])
            ->order(PaymentInstallment::FIELD_ORDER)
            ->find();

        return iterator_to_array($collection);
    }

    /**
     * @param PaymentInstallment[] $currentItems
     * @param InstallmentLine[] $saveItems
     */
    private function areDifferent(array $currentItems, array $saveItems): bool
    {
        if (count($currentItems) !== count($saveItems)) {
            return true;
        }

        foreach ($currentItems as $i => $entity) {
            if (!$saveItems[$i]->isEqualTo(InstallmentLine::fromEntity($entity))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param InstallmentLine[] $saveItems
     */
    private function populate(OrderEntity & PaymentTermsHavingOrder $order, array $saveItems): void
    {
        $this->deleteItems($order);

        foreach ($saveItems as $i => $item) {
            $itemEntity = $this->entityManager->getRDBRepositoryByClass(PaymentInstallment::class)->getNew();

            $itemEntity
                ->setOrder($i)
                ->setSource($order)
                ->setDate($item->date)
                ->setAmount($item->amount)
                ->setAmountLocal($item->amountLocal)
                ->setPercentage($item->percentage)
                ->setStatus($item->status);

            $this->entityManager->saveEntity($itemEntity);
        }
    }

    /**
     * @param PaymentInstallment[] $currentTerms
     * @param InstallmentLine[] $saveItems
     */
    private function updateStatuses(array $currentTerms, array $saveItems): void
    {
        if (count($saveItems) !== count($currentTerms)) {
            throw new LogicException();
        }

        foreach ($currentTerms as $i => $term) {
            if ($term->getStatus() === $saveItems[$i]->status) {
                continue;
            }

            $term->setStatus($saveItems[$i]->status);

            $this->entityManager->saveEntity($term);
        }
    }
}
