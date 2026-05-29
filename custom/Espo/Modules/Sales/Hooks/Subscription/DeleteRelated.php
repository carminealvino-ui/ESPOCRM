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

namespace Espo\Modules\Sales\Hooks\Subscription;

use Espo\Core\Field\DateTime;
use Espo\Core\Hook\Hook\AfterRemove;
use Espo\Core\Name\Field;
use Espo\Core\ORM\Repository\Option\RemoveOption;
use Espo\Entities\User;
use Espo\Modules\Sales\Entities\Subscription;
use Espo\Modules\Sales\Entities\SubscriptionItem;
use Espo\Modules\Sales\Entities\SubscriptionPeriod;
use Espo\Modules\Sales\Entities\SubscriptionUpdate;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Name\Attribute;
use Espo\ORM\Query\UpdateBuilder;
use Espo\ORM\Repository\Option\RemoveOptions;

/**
 * @implements AfterRemove<Subscription>
 */
class DeleteRelated implements AfterRemove
{
    public function __construct(
        private EntityManager $entityManager,
        private User $user,
    ) {}

    public function afterRemove(Entity $entity, RemoveOptions $options): void
    {
        $modifiedById = $options->get(RemoveOption::MODIFIED_BY_ID) ?? $this->user->getId();

        $this->removePeriods($entity, $modifiedById);
        $this->removeUpdates($entity, $modifiedById);
    }

    private function removePeriods(Subscription $entity, string $modifiedById): void
    {
        $update = UpdateBuilder::create()
            ->in(SubscriptionPeriod::ENTITY_TYPE)
            ->where([
                SubscriptionPeriod::ATTR_SUBSCRIPTION_ID => $entity->getId(),
                Attribute::DELETED => false,
            ])
            ->set([
                Attribute::DELETED => true,
                Field::MODIFIED_AT => DateTime::createNow()->toString(),
                Field::MODIFIED_BY . 'Id' => $modifiedById,
            ])
            ->build();

        $this->entityManager->getQueryExecutor()->execute($update);
    }

    private function removeUpdates(Subscription $entity, string $modifiedById): void
    {
        $this->removeUpdateItems($entity, $modifiedById);

        $update = UpdateBuilder::create()
            ->in(SubscriptionUpdate::ENTITY_TYPE)
            ->where([
                SubscriptionUpdate::ATTR_SUBSCRIPTION_ID => $entity->getId(),
                Attribute::DELETED => false,
            ])
            ->set([
                Attribute::DELETED => true,
                Field::MODIFIED_AT => DateTime::createNow()->toString(),
                Field::MODIFIED_BY . 'Id' => $modifiedById,
            ])
            ->build();

        $this->entityManager->getQueryExecutor()->execute($update);
    }

    private function removeUpdateItems(Subscription $entity, string $modifiedById): void
    {
        $link = SubscriptionItem::LINK_SUBSCRIPTION_UPDATE;

        $update = UpdateBuilder::create()
            ->in(SubscriptionItem::ENTITY_TYPE)
            ->where([
                $link . '.' . SubscriptionUpdate::ATTR_SUBSCRIPTION_ID => $entity->getId(),
                Attribute::DELETED => false,
            ])
            ->join($link)
            ->set([
                Attribute::DELETED => true,
                Field::MODIFIED_AT => DateTime::createNow()->toString(),
                Field::MODIFIED_BY . 'Id' => $modifiedById,
            ])
            ->build();

        $this->entityManager->getQueryExecutor()->execute($update);
    }
}
