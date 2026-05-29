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

use Espo\Core\Acl\AccessEntityCREDChecker;
use Espo\Core\Acl\ScopeData;
use Espo\Core\Acl\Table;
use Espo\Core\AclManager;
use Espo\Core\Portal\Acl\DefaultAccessChecker;
use Espo\Core\Portal\Acl\Traits\DefaultAccessCheckerDependency;
use Espo\Entities\User;
use Espo\Modules\Sales\Entities\OpportunityItem;
use Espo\Modules\Sales\Entities\QuoteItem;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\Modules\Sales\Tools\Sales\OrderEntityUtil;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * @implements AccessEntityCREDChecker<QuoteItem|OpportunityItem>
 */
class AccessChecker implements AccessEntityCREDChecker
{
    use DefaultAccessCheckerDependency;

    public function __construct(
        private DefaultAccessChecker $defaultAccessChecker,
        private AclManager $aclManager,
        private EntityManager $entityManager,
    ) {}

    public function checkEntityEdit(User $user, Entity $entity, ScopeData $data): bool
    {
        if (!$this->isItemListEditable($user, $entity)) {
            return false;
        }

        $parent = $this->getParent($entity);

        if (
            $parent instanceof OrderEntity &&
            $parent->isLocked()
        ) {
            return false;
        }

        return $this->defaultAccessChecker->checkEntityEdit($user, $entity, $data);
    }

    private function getParentEntityType(Entity $entity): string
    {
        return OrderEntityUtil::getItemParentEntityType($entity->getEntityType());
    }

    private function isItemListEditable(User $user, Entity $entity): bool
    {
        $entityType = $this->getParentEntityType($entity);

        return $this->aclManager->checkField($user, $entityType, OrderEntity::ATTR_ITEM_LIST, Table::ACTION_EDIT);
    }

    private function getParent(QuoteItem|OpportunityItem $entity): ?Entity
    {
        $parentField = OrderEntityUtil::getItemParentField($entity->getEntityType());

        return $this->entityManager
            ->getRelation($entity, $parentField)
            ->findOne();
    }
}
