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

namespace Espo\Modules\Sales\Hooks\SupplierCredit;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Core\Utils\Metadata;
use Espo\Modules\Sales\Entities\SupplierCredit;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\Modules\Sales\Tools\Sales\PostingDateHelper;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;
use RuntimeException;

/**
 * @implements BeforeSave<SupplierCredit>
 */
class SetValues implements BeforeSave
{
    public function __construct(
        private Metadata $metadata,
        private PostingDateHelper $postingDateHelper,
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        $this->setSupplierFields($entity);
        $this->setStateFields($entity);
    }

    private function setStateFields(SupplierCredit $entity): void
    {
        if (!$entity->has(OrderEntity::FIELD_STATUS)) {
            throw new RuntimeException("No 'status' attribute set.");
        }

        $doneStatusList = $this->metadata->get("scopes.{$entity->getEntityType()}.doneStatusList") ?? [];
        $canceledStatusList = $this->metadata->get("scopes.{$entity->getEntityType()}.canceledStatusList") ?? [];

        $isNotActual = in_array($entity->getStatus(), array_merge(
            $doneStatusList,
            $canceledStatusList,
        ));

        $entity->set('isDone', in_array($entity->getStatus(), $doneStatusList));
        $entity->set('isNotActual', $isNotActual);

        $this->postingDateHelper->process($entity);
    }

    private function setSupplierFields(SupplierCredit $entity): void
    {
        if (!$entity->isNew() && !$entity->isAttributeChanged(SupplierCredit::FIELD_SUPPLIER . 'Id')) {
            return;
        }

        if ($entity->isIssued()) {
            return;
        }

        $account = $entity->getSupplier()?->getAccount();

        $entity->setAccount($account);
    }
}
