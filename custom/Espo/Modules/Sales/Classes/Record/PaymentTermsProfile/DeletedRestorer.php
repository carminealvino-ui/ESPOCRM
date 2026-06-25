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

namespace Espo\Modules\Sales\Classes\Record\PaymentTermsProfile;

use Espo\Core\Field\DateTime;
use Espo\Core\Name\Field;
use Espo\Core\Record\Deleted\DefaultRestorer;
use Espo\Core\Record\Deleted\Restorer;
use Espo\Modules\Sales\Entities\PaymentTermsProfile;
use Espo\Modules\Sales\Entities\PaymentTermsProfileItem;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Name\Attribute;
use Espo\ORM\Query\UpdateBuilder;

/**
 * @implements Restorer<PaymentTermsProfile>
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
    }

    private function restoreItems(PaymentTermsProfile $entity, DateTime $modifiedAt): void
    {
        $update = UpdateBuilder::create()
            ->in(PaymentTermsProfileItem::ENTITY_TYPE)
            ->where([
                PaymentTermsProfileItem::ATTR_PROFILE_ID => $entity->getId(),
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
