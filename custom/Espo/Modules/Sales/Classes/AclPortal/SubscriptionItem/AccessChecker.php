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

namespace Espo\Modules\Sales\Classes\AclPortal\SubscriptionItem;

use Espo\Core\Acl\AccessEntityCREDChecker;
use Espo\Core\Acl\ScopeData;
use Espo\Core\Acl\Table;
use Espo\Core\Portal\Acl\DefaultAccessChecker;
use Espo\Core\Portal\Acl\Traits\DefaultAccessCheckerDependency;
use Espo\Core\AclManager;
use Espo\Entities\User;
use Espo\Modules\Sales\Entities\Subscription;
use Espo\Modules\Sales\Entities\SubscriptionItem;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\ORM\Entity;

/**
 * @implements AccessEntityCREDChecker<SubscriptionItem>
 */
class AccessChecker implements AccessEntityCREDChecker
{
    use DefaultAccessCheckerDependency;

    public function __construct(
        private DefaultAccessChecker $defaultAccessChecker,
        private AclManager $aclManager,
    ) {}

    public function checkEntityEdit(User $user, Entity $entity, ScopeData $data): bool
    {
        if (!$this->isItemListEditable($user)) {
            return false;
        }

        if ($entity->getSubscription()) {
            return $this->defaultAccessChecker->checkEntityEdit($user, $entity->getSubscription(), $data);
        }

        if ($entity->getSubscriptionUpdate()) {
            return $this->defaultAccessChecker->checkEntityEdit($user, $entity->getSubscriptionUpdate(), $data);
        }

        return false;
    }

    private function isItemListEditable(User $user): bool
    {
        return $this->aclManager
            ->checkField($user, Subscription::ENTITY_TYPE, OrderEntity::ATTR_ITEM_LIST, Table::ACTION_EDIT);
    }
}
