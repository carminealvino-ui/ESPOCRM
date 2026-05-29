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
use Espo\Core\Formula\Exceptions\Error as FormulaError;
use Espo\Core\ORM\Entity as CoreEntity;
use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\ORM\Entity;
use stdClass;

/**
 * @noinspection PhpUnused
 */
class UpdateCreatedEntity extends BaseEntity
{
    /**
     * @inheritDoc
     */
    protected function run(CoreEntity $entity, stdClass $actionData, array $options): bool
    {
        if (empty($actionData->target)) {
            return false;
        }

        $target = $actionData->target;

        $targetEntity = $this->getCreatedEntity($target);

        if (!$targetEntity) {
            return false;
        }

        if (property_exists($actionData, 'fields')) {
            $data = $this->getEntityValuesToSet($targetEntity, $actionData->fields);
            $targetEntity->setMultiple($data);
        }

        $this->processFormula($actionData, $targetEntity);

        if (!$targetEntity->has('modifiedById')) {
            $targetEntity->set('modifiedByName', 'System');
        }

        $this->entityManager->saveEntity($targetEntity, [SaveOption::MODIFIED_BY_ID => 'system']);

        return true;
    }

    /**
     * @throws Error
     */
    private function processFormula(stdClass $actionData, Entity $targetEntity): void
    {
        if (empty($actionData->formula)) {
            return;
        }

        $script = $actionData->formula;

        try {
            $this->formulaManager->run($script, $targetEntity, $this->getFormulaVariables());
        } catch (FormulaError $e) {
            throw new Error($e->getMessage(), previous: $e);
        }
    }
}
