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
use stdClass;

/**
 * @noinspection PhpUnused
 */
class RelateWithEntity extends BaseEntity
{
    /**
     * @throws Error
     */
    protected function run(CoreEntity $entity, stdClass $actionData, array $options): bool
    {
        if (empty($actionData->entityId) || empty($actionData->link)) {
            $message = 'Bad params defined in RelateWithEntity.';

            throw new Error($message);
        }

        $foreignEntityType = $entity->getRelationParam($actionData->link, 'entity');

        if (!$foreignEntityType) {
            $message = 'Could not find foreign entity type in RelateWithEntity.';

            throw new Error($message);
        }

        $foreignEntity = $this->entityManager->getEntityById($foreignEntityType, $actionData->entityId);

        if (!$foreignEntity) {
            $message = "Could not find foreign entity in RelateWithEntity.";

            throw new Error($message);
        }

        $relateOptions = [
            'context' => SaveContextHelper::obtainFromRawOptions($options),
        ];

        $this->entityManager
            ->getRelation($entity, $actionData->link)
            ->relate($foreignEntity, null, $relateOptions);

        if ($entity->hasLinkMultipleField($actionData->link)) {
            $entity->loadLinkMultipleField($actionData->link);
        }

        return true;
    }
}
