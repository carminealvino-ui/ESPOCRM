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

namespace Espo\Modules\Sales\Tools\Sales\Locker;

use Espo\Modules\Sales\Entities\DeliveryOrder;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Entities\PurchaseOrder;
use Espo\Modules\Sales\Entities\Quote;
use Espo\Modules\Sales\Entities\ReceiptOrder;
use Espo\Modules\Sales\Entities\ReturnOrder;
use Espo\Modules\Sales\Entities\SalesOrder;
use Espo\Modules\Sales\Entities\TransferOrder;
use Espo\ORM\EntityManager;

class Locker
{
    /** @var string[] */
    private array $entityTypes = [
        Quote::ENTITY_TYPE,
        Invoice::ENTITY_TYPE,
        SalesOrder::ENTITY_TYPE,
        DeliveryOrder::ENTITY_TYPE,
        PurchaseOrder::ENTITY_TYPE,
        ReceiptOrder::ENTITY_TYPE,
        ReturnOrder::ENTITY_TYPE,
        TransferOrder::ENTITY_TYPE,
    ];

    public function __construct(
        private EntityManager $entityManager
    ) {}

    /**
     * Lock not actual order records.
     */
    public function run(Params $params): void
    {
        foreach ($this->entityTypes as $entityType) {
            $this->processEntityType($entityType, $params);
        }
    }

    private function processEntityType(string $entityType, Params $params): void
    {
        $before = $params->getBefore();

        $collection = $this->entityManager
            ->getRDBRepository($entityType)
            ->sth()
            ->where([
                'isNotActual' => true,
                'isLocked' => false,
                /** @phpstan-ignore-next-line function.alreadyNarrowedType */
                'modifiedAt<=' => method_exists($before, 'toString') ?
                    $before->toString() :
                    $before->getString(),
            ])
            ->find();

        foreach ($collection as $entity) {
            $entity->set('isLocked', true);

            $this->entityManager->saveEntity($entity);
        }
    }
}
