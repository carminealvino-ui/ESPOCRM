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

namespace Espo\Modules\Sales\Hooks\PurchaseOrder;

use Espo\Modules\Sales\Entities\Supplier;
use Espo\Modules\Sales\Entities\PurchaseOrder;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/** @noinspection PhpUnused */
class PrepareBefore
{
    public static int $order = 7;

    public function __construct(
        private EntityManager $entityManager
    ) {}

    /**
     * @param PurchaseOrder $entity
     */
    public function beforeSave(Entity $entity): void
    {
        $this->setAccount($entity);
    }

    private function setAccount(PurchaseOrder $entity): void
    {
        if (!$entity->has('supplierId')) {
            return;
        }

        $supplierId = $entity->get('supplierId');

        if (!$supplierId) {
            /** @noinspection PhpRedundantOptionalArgumentInspection */
            $entity->set('accountId', null);

            return;
        }

        /** @var ?Supplier $supplier */
        $supplier = $this->entityManager->getEntityById(Supplier::ENTITY_TYPE, $supplierId);

        if (!$supplier) {
            /** @noinspection PhpRedundantOptionalArgumentInspection */
            $entity->set('accountId', null);

            return;
        }

        $entity->set('accountId', $supplier->get('accountId'));
    }
}
