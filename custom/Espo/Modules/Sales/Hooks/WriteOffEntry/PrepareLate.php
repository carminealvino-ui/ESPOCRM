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

namespace Espo\Modules\Sales\Hooks\WriteOffEntry;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Core\Utils\Metadata;
use Espo\Modules\Sales\Entities\WriteOffEntry;
use Espo\Modules\Sales\Tools\Sales\ConfigDataProvider;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * @implements BeforeSave<WriteOffEntry>
 */
class PrepareLate implements BeforeSave
{
    // After issuance.
    public static int $order = 21;

    public function __construct(
        private Metadata $metadata,
        private ConfigDataProvider $configDataProvider,
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        $this->setNumber($entity);
    }

    private function setNumber(WriteOffEntry $entity): void
    {
        if (!$this->metadata->get("entityDefs.{$entity->getEntityType()}.fields.number.useAutoincrement")) {
            return;
        }

        if ($entity->getFetched(OrderEntity::FIELD_WAS_ISSUED)) {
            return;
        }

        $field = OrderEntity::FIELD_NUMBER_A;

        if (!$entity->isIssued() && $this->configDataProvider->isDraftNumberingEnabled()) {
            $field = OrderEntity::FIELD_NUMBER_DRAFT_A;
        }

        if (!$entity->isAttributeWritten($field)) {
            return;
        }

        $entity->setNumber($entity->get($field));
    }
}
