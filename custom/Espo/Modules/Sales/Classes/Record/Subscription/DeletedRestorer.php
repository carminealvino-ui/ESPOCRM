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

namespace Espo\Modules\Sales\Classes\Record\Subscription;

use Espo\Core\Field\DateTime;
use Espo\Core\Name\Field;
use Espo\Core\Record\Deleted\Restorer;
use Espo\Modules\Sales\Classes\Record\Quote\DeletedRestorer as QuoteRestorer;
use Espo\Modules\Sales\Entities\Subscription;
use Espo\Modules\Sales\Entities\SubscriptionItem;
use Espo\Modules\Sales\Entities\SubscriptionPeriod;
use Espo\Modules\Sales\Entities\SubscriptionUpdate;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Name\Attribute;
use Espo\ORM\Query\UpdateBuilder;

/**
 * @implements Restorer<Subscription>
 */
class DeletedRestorer implements Restorer
{
    public function __construct(
        private EntityManager $entityManager,
        private QuoteRestorer $quoteRestorer,
    ) {}

    public function restore(Entity $entity): void
    {
        $modifiedAt = $entity->getModifiedAt();

        $this->quoteRestorer->restore($entity);

        if (!$modifiedAt) {
            return;
        }

        $this->restorePeriods($entity, $modifiedAt);
        $this->restoreUpdates($entity, $modifiedAt);
        $this->restoreUpdateItems($entity, $modifiedAt);
    }

    private function restorePeriods(Subscription $entity, DateTime $modifiedAt): void
    {
        $update = UpdateBuilder::create()
            ->in(SubscriptionPeriod::ENTITY_TYPE)
            ->where([
                SubscriptionPeriod::ATTR_SUBSCRIPTION_ID => $entity->getId(),
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

    private function restoreUpdates(Subscription $entity, DateTime $modifiedAt): void
    {
        $update = UpdateBuilder::create()
            ->in(SubscriptionUpdate::ENTITY_TYPE)
            ->where([
                SubscriptionUpdate::ATTR_SUBSCRIPTION_ID => $entity->getId(),
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

    private function restoreUpdateItems(Subscription $entity, DateTime $modifiedAt): void
    {
        $link = SubscriptionItem::LINK_SUBSCRIPTION_UPDATE;

        $update = UpdateBuilder::create()
            ->in(SubscriptionItem::ENTITY_TYPE)
            ->where([
                $link . '.' . SubscriptionUpdate::ATTR_SUBSCRIPTION_ID => $entity->getId(),
                Attribute::DELETED => true,
                Field::MODIFIED_AT . '>=' => $modifiedAt->toString(),
            ])
            ->join($link)
            ->set([
                Attribute::DELETED => false,
                Field::MODIFIED_AT => DateTime::createNow()->toString(),
            ])
            ->build();

        $this->entityManager->getQueryExecutor()->execute($update);
    }
}
