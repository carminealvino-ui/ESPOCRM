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

namespace Espo\Modules\Sales\Classes\Record\Invoice;

use Espo\Core\Field\DateTime;
use Espo\Core\Name\Field;
use Espo\Core\Record\Deleted\Restorer;
use Espo\Modules\Sales\Classes\Record\Quote\DeletedRestorer as QuoteRestorer;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Entities\PaymentInstallment;
use Espo\Modules\Sales\Entities\PaymentMeansItem;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Name\Attribute;
use Espo\ORM\Query\UpdateBuilder;

/**
 * @implements Restorer<Invoice>
 */
class DeletedRestorer implements Restorer
{
    public function __construct(
        private QuoteRestorer $quoteRestorer,
        private EntityManager $entityManager,
    ) {}

    public function restore(Entity $entity): void
    {
        $modifiedAt = $entity->getModifiedAt();

        $this->quoteRestorer->restore($entity);

        if (!$modifiedAt) {
            return;
        }

        $this->restorePaymentInstallments($entity, $modifiedAt);
        $this->restorePaymentMeansItems($entity, $modifiedAt);
    }

    private function restorePaymentInstallments(Invoice $entity, DateTime $modifiedAt): void
    {
        $update = UpdateBuilder::create()
            ->in(PaymentInstallment::ENTITY_TYPE)
            ->where([
                PaymentInstallment::FIELD_SOURCE . 'Type' => $entity->getEntityType(),
                PaymentInstallment::FIELD_SOURCE . 'Id' => $entity->getId(),
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

    private function restorePaymentMeansItems(Invoice $entity, DateTime $modifiedAt): void
    {
        $update = UpdateBuilder::create()
            ->in(PaymentMeansItem::ENTITY_TYPE)
            ->where([
                PaymentMeansItem::FIELD_SOURCE . 'Type' => $entity->getEntityType(),
                PaymentMeansItem::FIELD_SOURCE . 'Id' => $entity->getId(),
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
