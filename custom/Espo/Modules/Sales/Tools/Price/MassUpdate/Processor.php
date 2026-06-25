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

namespace Espo\Modules\Sales\Tools\Price\MassUpdate;

use Espo\Core\Acl;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\FieldValidation\FieldValidationManager;
use Espo\Core\MassAction\Data;
use Espo\Core\MassAction\Params;
use Espo\Core\MassAction\QueryBuilder;
use Espo\Core\MassAction\Result;
use Espo\Core\Record\ActionHistory\Action as RecordAction;
use Espo\Core\Record\ServiceContainer;
use Espo\Entities\User;
use Espo\Modules\Sales\Entities\PriceRule;
use Espo\ORM\Collection;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * @noinspection PhpUnused
 */
class Processor
{
    /**
     * @param PriceSetter<Entity> $priceSetter
     */
    public function __construct(
        private Acl $acl,
        private QueryBuilder $queryBuilder,
        private EntityManager $entityManager,
        private ServiceContainer $serviceContainer,
        private User $user,
        private FieldValidationManager $fieldValidationManager,
        private PriceSetter $priceSetter
    ) {}

    /**
     * @throws BadRequest
     * @throws Forbidden
     */
    public function process(Params $params, Data $data): Result
    {
        $this->check($params);

        $rule = $this->getRule($data);
        $targetField = $this->getTargetField($data);
        $collection = $this->getCollection($params);

        $count = 0;

        foreach ($collection as $entity) {
            $is = $this->processEntity($entity, $rule, $targetField);

            if ($is) {
                $count++;
            }
        }

        return new Result($count);
    }

    /**
     * @throws Forbidden
     */
    private function check(Params $params): void
    {
        if ($this->acl->getPermissionLevel('massUpdate') !== Acl\Table::LEVEL_YES) {
            throw new Forbidden("No 'massUpdate' permission.");
        }

        if (!$this->acl->checkScope($params->getEntityType(), Acl\Table::ACTION_EDIT)) {
            throw new Forbidden("No edit access.");
        }
    }

    private function processEntity(Entity $entity, PriceRule $rule, ?string $targetField): bool
    {
        if (!$this->acl->checkEntity($entity, Acl\Table::ACTION_EDIT)) {
            return false;
        }

        $this->priceSetter->set($entity, $rule, $targetField);

        if (!$this->isChanged($entity)) {
            return false;
        }

        if (!$this->isValid($entity)) {
            return false;
        }

        $this->entityManager->saveEntity($entity, [
            'massUpdate' => true,
            'modifiedById' => $this->user->getId(),
        ]);

        $service = $this->serviceContainer->get($entity->getEntityType());

        $service->processActionHistoryRecord(RecordAction::UPDATE, $entity);

        return true;
    }

    /**
     * @return Collection<Entity>
     * @throws BadRequest
     * @throws Forbidden
     */
    private function getCollection(Params $params): Collection
    {
        /** @var Collection<Entity> */
        return $this->entityManager
            ->getRDBRepository($params->getEntityType())
            ->clone($this->queryBuilder->build($params))
            ->sth()
            ->find();
    }

    /**
     * @throws BadRequest
     */
    private function getRule(Data $data): PriceRule
    {
        $discount = $data->get('discount');
        $roundingMethod = $data->get('roundingMethod') ?? PriceRule::ROUNDING_METHOD_HALF_UP;
        $roundingFactor = $data->get('roundingFactor') ?? 0.01;
        $surcharge = $data->get('surcharge');

        if ($discount !== null && !is_float($discount) && !is_int($discount)) {
            throw new BadRequest("Bad 'discount' $discount value.");
        }

        if ($discount !== null) {
            $discount = (float) $discount;

            if (abs($discount) > 100) {
                throw new BadRequest("Bad 'discount' value. Should not be more than 100.");
            }
        }

        if (!is_float($roundingFactor)) {
            throw new BadRequest("Bad 'roundingFactor' value.");
        }

        if ($surcharge !== null && !is_float($surcharge) && !is_int($surcharge)) {
            throw new BadRequest("Bad 'surcharge'$surcharge value.");
        }

        if (
            !in_array($roundingMethod, [
                PriceRule::ROUNDING_METHOD_DOWN,
                PriceRule::ROUNDING_METHOD_HALF_UP,
                PriceRule::ROUNDING_METHOD_UP,
            ])
        ) {
            throw new BadRequest("Bad 'roundingMethod' value.");
        }

        $rule = $this->entityManager->getRDBRepositoryByClass(PriceRule::class)->getNew();

        $rule
            ->setDiscount($discount)
            ->setRoundingMethod($roundingMethod)
            ->setRoundingFactor($roundingFactor)
            ->setSurcharge($surcharge);

        return $rule;
    }

    /**
     * @throws BadRequest
     */
    private function getTargetField(Data $data): ?string
    {
        $targetField = $data->get('targetField');

        if ($targetField !== null && !is_string($targetField)) {
            throw new BadRequest("Bad 'targetField'.");
        }

        return $targetField;
    }

    private function isChanged(Entity $entity): bool
    {
        $isChanged = false;

        foreach ($entity->getAttributeList() as $attribute) {
            if ($entity->isAttributeChanged($attribute)) {
                $isChanged = true;

                break;
            }
        }

        return $isChanged;
    }

    private function isValid(Entity $entity): bool
    {
        $entityDefs = $this->entityManager->getDefs()->getEntity($entity->getEntityType());

        foreach ($this->fieldValidationManager->processAll($entity) as $failure) {
            $field = $failure->getField();

            if ($entityDefs->getField($field)->getType() === 'currency') {
                return false;
            }

        }

        return true;
    }
}
