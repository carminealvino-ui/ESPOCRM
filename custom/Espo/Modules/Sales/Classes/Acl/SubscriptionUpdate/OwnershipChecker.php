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

namespace Espo\Modules\Sales\Classes\Acl\SubscriptionUpdate;

use Espo\Core\Acl\DefaultOwnershipChecker;
use Espo\Core\Acl\OwnershipOwnChecker;
use Espo\Core\Acl\OwnershipTeamChecker;
use Espo\Core\AclManager;
use Espo\Entities\User;
use Espo\Modules\Sales\Entities\Subscription;
use Espo\Modules\Sales\Entities\SubscriptionUpdate;
use Espo\ORM\Entity;
use UnexpectedValueException;

/**
 * @implements OwnershipOwnChecker<SubscriptionUpdate>
 * @implements OwnershipTeamChecker<SubscriptionUpdate>
 */
class OwnershipChecker implements OwnershipOwnChecker, OwnershipTeamChecker
{
    public function __construct(
        /** @phpstan-ignore-next-line property.onlyWritten */
        private DefaultOwnershipChecker $defaultOwnershipChecker,
        private AclManager $aclManager,
    ) {}

    public function checkOwn(User $user, Entity $entity): bool
    {
        $parent = $this->getSubscription($entity);

        if ($parent) {
            return $this->aclManager->checkOwnershipOwn($user, $parent);
        }

        return $this->defaultOwnershipChecker->checkOwn($user, $entity);
    }

    public function checkTeam(User $user, Entity $entity): bool
    {
        $parent = $this->getSubscription($entity);

        if ($parent) {
            return $this->aclManager->checkOwnershipTeam($user, $parent);
        }

        return $this->defaultOwnershipChecker->checkTeam($user, $entity);
    }

    private function getSubscription(SubscriptionUpdate $entity): ?Subscription
    {
        try {
            return $entity->getSubscription();
        } catch (UnexpectedValueException) {}

        return null;
    }
}
