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

namespace Espo\Modules\Sales\Hooks\SalesOrder;

use Espo\Core\Utils\Metadata;
use Espo\Modules\Sales\Entities\SalesOrder;
use Espo\ORM\Entity;

/**
 * @noinspection PhpUnused
 */
class SetValues
{
    public function __construct(
        private Metadata $metadata
    ) {}

    /**
     * @param SalesOrder $entity
     * @param array<string, mixed> $options
     * @noinspection PhpUnusedParameterInspection
     */
    public function beforeSave(Entity $entity, array $options): void
    {
        $this->setStateValues($entity);
    }

    private function setStateValues(SalesOrder $entity): void
    {
        if (!$entity->has('status')) {
            return;
        }

        $doneStatusList = $this->metadata->get("scopes.{$entity->getEntityType()}.doneStatusList") ?? [];
        $canceledStatusList = $this->metadata->get("scopes.{$entity->getEntityType()}.canceledStatusList") ?? [];

        $isNotActual = in_array($entity->getStatus(), array_merge(
            $doneStatusList,
            $canceledStatusList,
        ));

        $entity->set('isDone', in_array($entity->getStatus(), $doneStatusList));
        $entity->set('isNotActual', $isNotActual);
    }
}
