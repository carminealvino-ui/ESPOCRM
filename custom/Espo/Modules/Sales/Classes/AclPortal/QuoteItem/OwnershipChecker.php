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

namespace Espo\Modules\Sales\Classes\AclPortal\QuoteItem;

use Espo\Core\Acl\OwnershipOwnChecker;
use Espo\Core\AclManager;
use Espo\Core\Portal\Acl\DefaultOwnershipChecker;
use Espo\Core\Portal\Acl\OwnershipAccountChecker;
use Espo\Core\Portal\Acl\OwnershipContactChecker;
use Espo\Core\Portal\AclManager as AclManagerPortal;
use Espo\Entities\User;
use Espo\Modules\Sales\Entities\QuoteItem;
use Espo\Modules\Sales\Tools\Sales\OrderEntityUtil;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use RuntimeException;

/**
 * @implements OwnershipAccountChecker<QuoteItem>
 * @implements OwnershipContactChecker<QuoteItem>
 * @implements OwnershipOwnChecker<QuoteItem>
 */
class OwnershipChecker implements OwnershipAccountChecker, OwnershipContactChecker, OwnershipOwnChecker
{
    private DefaultOwnershipChecker $defaultOwnershipChecker;
    private AclManagerPortal $aclManager;
    private EntityManager $entityManager;

    public function __construct(
        DefaultOwnershipChecker $defaultOwnershipChecker,
        AclManager $aclManager,
        EntityManager $entityManager
    ) {
        if (!$aclManager instanceof AclManagerPortal) {
            throw new RuntimeException("Wrong AclManager.");
        }

        $this->defaultOwnershipChecker = $defaultOwnershipChecker;
        $this->aclManager = $aclManager;
        $this->entityManager = $entityManager;
    }

    public function checkAccount(User $user, Entity $entity): bool
    {
        $parentField = $this->getParentField($entity);

        if (!$entity->has($parentField . 'Id')) {
            return $this->defaultOwnershipChecker->checkAccount($user, $entity);
        }

        $parent = $this->entityManager
            ->getRDBRepository($entity->getEntityType())
            ->getRelation($entity, $parentField)
            ->findOne();

        if (!$parent) {
            return false;
        }

        return $this->aclManager->checkOwnershipAccount($user, $parent);
    }

    public function checkContact(User $user, Entity $entity): bool
    {
        $parentField = $this->getParentField($entity);

        if (!$entity->has($parentField . 'Id')) {
            return $this->defaultOwnershipChecker->checkContact($user, $entity);
        }

        $parent = $this->entityManager
            ->getRDBRepository($entity->getEntityType())
            ->getRelation($entity, $parentField)
            ->findOne();

        if (!$parent) {
            return false;
        }

        return $this->aclManager->checkOwnershipContact($user, $parent);
    }

    public function checkOwn(User $user, Entity $entity): bool
    {
        $parentField = $this->getParentField($entity);

        if (!$entity->has($parentField . 'Id')) {
            return $this->defaultOwnershipChecker->checkOwn($user, $entity);
        }

        $parent = $this->entityManager
            ->getRDBRepository($entity->getEntityType())
            ->getRelation($entity, $parentField)
            ->findOne();

        if (!$parent) {
            return false;
        }

        return $this->aclManager->checkOwnershipOwn($user, $parent);
    }

    private function getParentField(Entity $entity): string
    {
        return OrderEntityUtil::getItemParentField($entity->getEntityType());
    }
}
