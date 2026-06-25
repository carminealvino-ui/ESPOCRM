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

namespace Espo\Modules\Sales\Classes\Acl\SubscriptionItem;

use Espo\Core\Acl\OwnershipOwnChecker;
use Espo\Core\Acl\OwnershipTeamChecker;
use Espo\Core\AclManager;
use Espo\Entities\User;
use Espo\Modules\Sales\Entities\SubscriptionItem;
use Espo\ORM\Entity;

/**
 * @implements OwnershipOwnChecker<SubscriptionItem>
 * @implements OwnershipTeamChecker<SubscriptionItem>
 */
class OwnershipChecker implements OwnershipOwnChecker, OwnershipTeamChecker
{
    public function __construct(
        private AclManager $aclManager,
    ) {}

    public function checkOwn(User $user, Entity $entity): bool
    {
        if ($entity->getSubscription()) {
            return $this->aclManager->checkOwnershipOwn($user, $entity->getSubscription());
        }

        if ($entity->getSubscriptionUpdate()) {
            return $this->aclManager->checkOwnershipOwn($user, $entity->getSubscriptionUpdate());
        }

        return false;
    }

    public function checkTeam(User $user, Entity $entity): bool
    {
        if ($entity->getSubscription()) {
            return $this->aclManager->checkOwnershipTeam($user, $entity->getSubscription());
        }

        if ($entity->getSubscriptionUpdate()) {
            return $this->aclManager->checkOwnershipTeam($user, $entity->getSubscriptionUpdate());
        }

        return false;
    }
}
