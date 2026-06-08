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

use Espo\Modules\Sales\Entities\PurchaseOrder;
use Espo\Modules\Sales\Entities\ReceiptOrder;
use Espo\Modules\Sales\Entities\ReturnOrder;
use Espo\Modules\Sales\Tools\Quote\BeforeSaveProcessor;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class Prepare
{
    // Before SetValues.
    public static int $order = 8;

    public function __construct(
        private BeforeSaveProcessor $beforeSaveProcessor,
        private EntityManager $entityManager
    ) {}

    /**
     * @param ReceiptOrder $entity
     */
    public function beforeSave(Entity $entity): void
    {
        $this->beforeSaveProcessor->process($entity);

        $this->setAccount($entity);
    }

    private function setAccount(ReceiptOrder $entity): void
    {
        if ($entity->getAccount()) {
            return;
        }

        if ($entity->getPurchaseOrder()) {
            $purchaseOrder = $this->entityManager
                ->getRDBRepositoryByClass(PurchaseOrder::class)
                ->getById($entity->getPurchaseOrder()->getId());

            if (!$purchaseOrder || !$purchaseOrder->getAccount()) {
                return;
            }

            $entity->set('accountId', $purchaseOrder->getAccount()->getId());

            return;
        }

        if ($entity->getReturnOrder()) {
            $returnOrder = $this->entityManager
                ->getRDBRepositoryByClass(ReturnOrder::class)
                ->getById($entity->getReturnOrder()->getId());

            if (!$returnOrder || !$returnOrder->getAccount()) {
                return;
            }

            $entity->set('accountId', $returnOrder->getAccount()->getId());
        }
    }
}
