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
 * License ID: 3fed3a66185f29dcda0e14c6ee94bf07
 ************************************************************************************/

namespace Espo\Modules\Advanced\Core\Workflow\Actions;

use Espo\Core\Exceptions\Error;
use Espo\Core\ORM\Entity as CoreEntity;
use Espo\Modules\Advanced\Tools\Workflow\Core\SaveContextHelper;
use Espo\ORM\Entity;
use stdClass;

/**
 * @noinspection PhpUnused
 */
class UnrelateFromEntity extends BaseEntity
{
    /**
     * @throws Error
     */
    protected function run(CoreEntity $entity, stdClass $actionData, array $options): bool
    {
        $foreignEntity = $this->getForeignEntity($entity, $actionData);

        $relateOptions = [
            'context' => SaveContextHelper::obtainFromRawOptions($options),
        ];

        $this->entityManager
            ->getRelation($entity, $actionData->link)
            ->unrelate($foreignEntity, $relateOptions);

        if ($entity->hasLinkMultipleField($actionData->link)) {
            $entity->loadLinkMultipleField($actionData->link);
        }

        return true;
    }

    /**
     * @throws Error
     */
    private function getForeignEntity(CoreEntity $entity, stdClass $actionData): Entity
    {
        if (empty($actionData->entityId) || empty($actionData->link)) {
            throw new Error("Workflow {$this->getWorkflowId()}: Bad params defined in UnrelateFromEntity.");
        }

        $foreignEntityType = $entity->getRelationParam($actionData->link, 'entity');

        if (!$foreignEntityType) {
            $message = "Workflow {$this->getWorkflowId()}: Could not find foreign entity type in UnrelateFromEntity.";

            throw new Error($message);
        }

        $foreignEntity = $this->entityManager->getEntityById($foreignEntityType, $actionData->entityId);

        if (!$foreignEntity) {
            $message = "Workflow {$this->getWorkflowId()}: Could not find foreign entity in UnrelateFromEntity.";

            throw new Error($message);
        }

        return $foreignEntity;
    }
}
