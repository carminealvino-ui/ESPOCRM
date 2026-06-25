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

namespace Espo\Modules\Sales\Classes\Record\Quote;

use Espo\Core\Field\DateTime;
use Espo\Core\Name\Field;
use Espo\Core\Record\Deleted\DefaultRestorer;
use Espo\Core\Record\Deleted\Restorer;
use Espo\Modules\Sales\Entities\TaxAllocationItem;
use Espo\Modules\Sales\Entities\TaxLineItem;
use Espo\Modules\Sales\Entities\TaxTotalItem;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\Modules\Sales\Tools\Sales\OrderEntityUtil;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Name\Attribute;
use Espo\ORM\Query\UpdateBuilder;

/**
 * @implements Restorer<OrderEntity>
 */
class DeletedRestorer implements Restorer
{
    public function __construct(
        private DefaultRestorer $defaultRestorer,
        private EntityManager $entityManager,
    ) {}

    public function restore(Entity $entity): void
    {
        $modifiedAt = $entity->getModifiedAt();

        $this->defaultRestorer->restore($entity);

        if (!$modifiedAt) {
            return;
        }

        $this->restoreItems($entity, $modifiedAt);
        $this->restoreTaxLineItems($entity, $modifiedAt);
        $this->restoreTaxTotalItems($entity, $modifiedAt);
        $this->restoreTaxAllocationItems($entity, $modifiedAt);
    }

    private function restoreItems(OrderEntity $entity, DateTime $modifiedAt): void
    {
        $entityType = OrderEntityUtil::getItemEntityType($entity->getEntityType());
        $parentIdAttribute = lcfirst($entity->getEntityType()) . 'Id';

        $update = UpdateBuilder::create()
            ->in($entityType)
            ->where([
                $parentIdAttribute => $entity->getId(),
                Attribute::DELETED => true,
                Field::MODIFIED_AT . '>=' => $modifiedAt->toString(),
            ])
            ->set([
                Attribute::DELETED => false,
                Field::MODIFIED_AT => DateTime::createNow()->toString(),
            ])
            ->build();

        $this->entityManager->getQueryExecutor()->execute($update);
    }

    private function restoreTaxLineItems(OrderEntity $entity, DateTime $modifiedAt): void
    {
        if (!OrderEntityUtil::isWithTax($entity->getEntityType())) {
            return;
        }

        $update = UpdateBuilder::create()
            ->in(TaxLineItem::ENTITY_TYPE)
            ->where([
                TaxLineItem::FIELD_SOURCE . 'Id' => $entity->getId(),
                TaxLineItem::FIELD_SOURCE . 'Type' => $entity->getEntityType(),
                Attribute::DELETED => true,
                Field::MODIFIED_AT . '>=' => $modifiedAt->toString(),
            ])
            ->set([
                Attribute::DELETED => false,
                Field::MODIFIED_AT => DateTime::createNow()->toString(),
            ])
            ->build();

        $this->entityManager->getQueryExecutor()->execute($update);
    }

    private function restoreTaxTotalItems(OrderEntity $entity, DateTime $modifiedAt): void
    {
        if (!OrderEntityUtil::isWithTax($entity->getEntityType())) {
            return;
        }

        $update = UpdateBuilder::create()
            ->in(TaxTotalItem::ENTITY_TYPE)
            ->where([
                TaxTotalItem::FIELD_SOURCE . 'Id' => $entity->getId(),
                TaxTotalItem::FIELD_SOURCE . 'Type' => $entity->getEntityType(),
                Attribute::DELETED => true,
                Field::MODIFIED_AT . '>=' => $modifiedAt->toString(),
            ])
            ->set([
                Attribute::DELETED => false,
                Field::MODIFIED_AT => DateTime::createNow()->toString(),
            ])
            ->build();

        $this->entityManager->getQueryExecutor()->execute($update);
    }

    private function restoreTaxAllocationItems(OrderEntity|Entity $entity, DateTime $modifiedAt): void
    {
        if (!OrderEntityUtil::isWithTaxCashBasis($entity->getEntityType())) {
            return;
        }

        $update = UpdateBuilder::create()
            ->in(TaxAllocationItem::ENTITY_TYPE)
            ->where([
                TaxAllocationItem::FIELD_SOURCE . 'Id' => $entity->getId(),
                TaxAllocationItem::FIELD_SOURCE . 'Type' => $entity->getEntityType(),
                Attribute::DELETED => true,
                Field::MODIFIED_AT . '>=' => $modifiedAt->toString(),
            ])
            ->set([
                Attribute::DELETED => false,
                Field::MODIFIED_AT => DateTime::createNow()->toString(),
            ])
            ->build();

        $this->entityManager->getQueryExecutor()->execute($update);
    }
}
