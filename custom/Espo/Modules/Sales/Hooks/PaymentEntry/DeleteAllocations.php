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

namespace Espo\Modules\Sales\Hooks\PaymentEntry;

use Espo\Core\Hook\Hook\AfterRemove;
use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\Modules\Sales\Entities\PaymentEntry;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\RemoveOptions;

/**
 * @implements AfterRemove<PaymentEntry>
 */
class DeleteAllocations implements AfterRemove
{
    public function __construct(
        private EntityManager $entityManager,
    ) {}

    public function afterRemove(Entity $entity, RemoveOptions $options): void
    {
        $allocations = $this->entityManager
            ->getRelation($entity, PaymentEntry::RELATION_ALLOCATIONS)
            ->find();

        foreach ($allocations as $allocation) {
            $this->entityManager->removeEntity($allocation, [
                SaveOption::SILENT => (bool) $options->get(SaveOption::SILENT),
            ]);
        }
    }
}
