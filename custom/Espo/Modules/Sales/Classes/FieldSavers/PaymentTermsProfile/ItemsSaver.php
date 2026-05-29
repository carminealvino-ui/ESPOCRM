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

namespace Espo\Modules\Sales\Classes\FieldSavers\PaymentTermsProfile;

use Espo\Core\FieldProcessing\Saver;
use Espo\Core\FieldProcessing\Saver\Params;
use Espo\Modules\Sales\Entities\PaymentTermsProfile;
use Espo\Modules\Sales\Entities\PaymentTermsProfileItem;
use Espo\Modules\Sales\Tools\PaymentTerms\ProfileTermItem;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Query\DeleteBuilder;

/**
 * @implements Saver<PaymentTermsProfile>
 */
class ItemsSaver implements Saver
{
    public function __construct(
        private EntityManager $entityManager,
    ) {}

    public function process(Entity $entity, Params $params): void
    {
        if (
            !$entity->isAttributeChanged(PaymentTermsProfile::FIELD_ITEMS)
        ) {
            return;
        }

        if (!$entity->isNew()) {
            $this->deleteItems($entity);
        }

        $this->insertItems($entity);
    }

    private function deleteItems(PaymentTermsProfile $entity): void
    {
        $query = DeleteBuilder::create()
            ->from(PaymentTermsProfileItem::ENTITY_TYPE)
            ->where([
                PaymentTermsProfileItem::ATTR_PROFILE_ID => $entity->getId(),
            ])
            ->build();

        $this->entityManager->getQueryExecutor()->execute($query);
    }

    private function insertItems(PaymentTermsProfile $entity): void
    {
        foreach ($entity->getItems() as $i => $item) {
            $this->insertItem($entity, $item, $i);
        }
    }

    private function insertItem(PaymentTermsProfile $entity, ProfileTermItem $item, int $order): void
    {
        $itemEntity = $this->entityManager->getRDBRepositoryByClass(PaymentTermsProfileItem::class)->getNew();

        $itemEntity
            ->setProfileId($entity->getId())
            ->setOrder($order)
            ->setPercentage($item->percentage)
            ->setDays($item->days);

        $this->entityManager->saveEntity($itemEntity);
    }
}
