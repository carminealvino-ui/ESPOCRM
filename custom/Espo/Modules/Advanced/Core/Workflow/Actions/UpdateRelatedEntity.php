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
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\ORM\Type\RelationType;
use stdClass;

/**
 * @noinspection PhpUnused
 */
class UpdateRelatedEntity extends BaseEntity
{
    /**
     * @throws Error
     */
    protected function run(CoreEntity $entity, stdClass $actionData, array $options): bool
    {
        $link = $actionData->link;

        $relatedEntities = $this->getRelatedEntities($entity, $link);

        $saveOptions = [
            'modifiedById' => 'system',
            'workflowId' => $this->getWorkflowId(),
        ];

        foreach ($relatedEntities as $relatedEntity) {
            if (!$relatedEntity instanceof Entity) {
                continue;
            }

            $update = true;

            if (
                $entity->hasRelation($link) &&
                $entity->getRelationType($link) === RelationType::BELONGS_TO_PARENT &&
                !empty($actionData->parentEntityType) &&
                $actionData->parentEntityType !== $relatedEntity->getEntityType()
            ) {
                $update = false;
            }

            if (!$update) {
                continue;
            }

            $data = $this->getEntityValuesToSet($relatedEntity, $actionData->fields);
            $relatedEntity->setMultiple($data);

            $this->processFormula($actionData, $relatedEntity);

            if (!$relatedEntity->has('modifiedById')) {
                $relatedEntity->set('modifiedByName', 'System');
            }

            $this->entityManager->saveEntity($relatedEntity, $saveOptions);
        }

        return true;
    }

    /**
     * @return EntityCollection|iterable<Entity>
     */
    protected function getRelatedEntities(Entity $entity, string $link)
    {
        if (empty($link) || !$entity->hasRelation($link)) {
            return [];
        }

        switch ($entity->getRelationType($link)) {
            case Entity::BELONGS_TO_PARENT:
                $parentType = $entity->get($link . 'Type');
                $parentId = $entity->get($link . 'Id');

                if (!$parentType || !$parentId) {
                    return [];
                }

                $relatedEntity = $this->entityManager->getEntityById($parentType, $parentId);

                if (!$relatedEntity) {
                    return [];
                }

                $fetched = $this->entityManager
                    ->getCollectionFactory()
                    ->create($entity->getEntityType(), [$relatedEntity]);

                break;

            case Entity::HAS_MANY:
            case Entity::HAS_CHILDREN:
            case Entity::MANY_MANY:
                $fetched = $this->entityManager
                    ->getRelation($entity, $link)
                    ->sth()
                    ->find();

                break;

            default:
                $fetched = $this->entityManager
                    ->getRelation($entity, $link)
                    ->findOne();

                break;
        }

        if ($fetched instanceof Entity) {
            return $this->entityManager
                ->getCollectionFactory()
                ->create($entity->getEntityType(), [$fetched]);
        }

        return $fetched ?? [];
    }


    /**
     * @throws Error
     */
    private function processFormula(stdClass $actionData, Entity $entity): void
    {
        if (empty($actionData->formula)) {
            return;
        }

        $clonedVariables = clone $this->getFormulaVariables();

        try {
            $this->formulaManager->run($actionData->formula, $entity, $clonedVariables);
        } catch (FormulaError $e) {
            throw new Error($e->getMessage(), previous: $e);
        }
    }
}
