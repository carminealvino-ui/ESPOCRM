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

namespace Espo\Modules\Sales\Tools\Inventory\Detach;

use Espo\Modules\Sales\Entities\DeliveryOrder;
use Espo\Modules\Sales\Entities\InventoryTransaction;
use Espo\Modules\Sales\Entities\ReceiptOrder;
use Espo\Modules\Sales\Entities\SalesOrder;
use Espo\Modules\Sales\Entities\TransferOrder;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\ORM\EntityManager;
use Espo\ORM\Query\Part\Condition as Cond;
use Espo\ORM\Query\Part\Expression as Expr;
use Espo\ORM\Query\SelectBuilder;
use Espo\ORM\Query\UpdateBuilder;

/**
 * Detaches inventory transactions from their parent record.
 * Parent records must be locked or deleted to be detached from.
 * After that, detached transactions can be compressed.
 */
class Detach
{
    /** @var string[] */
    private array $entityTypes = [
        DeliveryOrder::ENTITY_TYPE,
        ReceiptOrder::ENTITY_TYPE,
        TransferOrder::ENTITY_TYPE,
        SalesOrder::ENTITY_TYPE,
    ];

    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function run(Params $params): Result
    {
        $counter = new Counter();

        foreach ($this->entityTypes as $entityType) {
            $this->processEntityType($entityType, $counter, $params);
        }

        return new Result($counter->getValue());
    }

    private function processEntityType(string $entityType, Counter $counter, Params $params): void
    {
        $this->processEntityTypeDeleted($entityType, $counter);

        $before = $params->getBefore();

        $builder = $this->entityManager
            ->getRDBRepository($entityType)
            ->sth()
            ->where([
                'isLocked' => true,
                'isHardLocked' => false,
            ]);

        if ($before) {
            $builder->where([
                /** @phpstan-ignore-next-line function.alreadyNarrowedType */
                'modifiedAt<=' => method_exists($before, 'toString') ?
                    $before->toString() :
                    $before->getString(),
            ]);
        }

        /** @var iterable<OrderEntity> $collection */
        $collection = $builder->find();

        foreach ($collection as $entity) {
            $this->processEntity($entity, $counter);
        }
    }

    private function processEntity(OrderEntity $entity, Counter $counter): void
    {
        $this->entityManager
            ->getTransactionManager()
            ->run(fn () => $this->processEntityInTransaction($entity, $counter));
    }

    private function processEntityInTransaction(OrderEntity $entity, Counter $counter): void
    {
        $this->lock($entity);

        $countBefore = $this->getTransactionCount($entity);

        $this->detachTransactionsForEntity($entity);

        $counter->add($countBefore);
    }

    private function detachTransactionsForEntity(OrderEntity $entity): void
    {
        $updateQuery = UpdateBuilder::create()
            ->in(InventoryTransaction::ENTITY_TYPE)
            ->where([
                'parentId' => $entity->getId(),
                'parentType' => $entity->getEntityType(),
            ])
            ->set([
                'parentId' => null,
                'parentType' => null,
            ])
            ->build();

        $this->entityManager->getQueryExecutor()->execute($updateQuery);

        $entity->set('isHardLocked', true);
        $this->entityManager->saveEntity($entity);
    }

    private function lock(OrderEntity $entity): void
    {
        $this->entityManager
            ->getRDBRepositoryByClass(InventoryTransaction::class)
            ->sth()
            ->select('id')
            ->forUpdate()
            ->where([
                'parentId' => $entity->getId(),
                'parentType' => $entity->getEntityType(),
            ])
            ->find();
    }

    private function getTransactionCount(OrderEntity $entity): int
    {
        return $this->entityManager
            ->getRDBRepositoryByClass(InventoryTransaction::class)
            ->where([
                'parentId' => $entity->getId(),
                'parentType' => $entity->getEntityType(),
            ])
            ->count();
    }

    private function processEntityTypeDeleted(string $entityType, Counter $counter): void
    {
        $this->entityManager
            ->getTransactionManager()
            ->run(fn () => $this->processEntityTypeDeletedInTransaction($entityType, $counter));

    }

    private function processEntityTypeDeletedInTransaction(string $entityType, Counter $counter): void
    {
        $this->lockEntityType($entityType);

        $countBefore = $this->getEntityTypeTransactionCount($entityType);

        $selectQuery = SelectBuilder::create()
            ->from(InventoryTransaction::ENTITY_TYPE, 'i')
            ->select('id')
            ->where(['parentType' => $entityType])
            ->where(
                Cond::not(
                    Cond::exists(
                        SelectBuilder::create()
                            ->from($entityType)
                            ->select('id')
                            ->where(['id:' => 'i.parentId'])
                            ->withDeleted()
                            ->build()
                    )
                )
            )
            ->build();

        $updateQuery = UpdateBuilder::create()
            ->in(InventoryTransaction::ENTITY_TYPE)
            ->set([
                'parentType' => null,
                'parentId' => null,
            ])
            ->where(
                Cond::in(
                    Expr::column('id'),
                    $selectQuery
                )
            )
            ->build();

        $this->entityManager->getQueryExecutor()->execute($updateQuery);

        $countAfter = $this->getEntityTypeTransactionCount($entityType);

        $counter->add($countBefore - $countAfter);
    }

    private function lockEntityType(string $entityType): void
    {
        $this->entityManager
            ->getRDBRepositoryByClass(InventoryTransaction::class)
            ->sth()
            ->select('id')
            ->forUpdate()
            ->where([
                'parentType' => $entityType,
            ])
            ->find();
    }

    private function getEntityTypeTransactionCount(string $entityType): int
    {
        return $this->entityManager
            ->getRDBRepositoryByClass(InventoryTransaction::class)
            ->where([
                'parentType' => $entityType,
            ])
            ->count();
    }
}
