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

use Espo\Core\Acl\AccessEntityCREDChecker;
use Espo\Core\Acl\DefaultAccessChecker;
use Espo\Core\Acl\ScopeData;
use Espo\Core\Acl\Table;
use Espo\Core\Acl\Traits\DefaultAccessCheckerDependency;
use Espo\Core\AclManager;
use Espo\Entities\User;
use Espo\Modules\Sales\Entities\Subscription;
use Espo\Modules\Sales\Entities\SubscriptionUpdate;
use Espo\ORM\Entity;
use UnexpectedValueException;

/**
 * @implements AccessEntityCREDChecker<SubscriptionUpdate>
 */
class AccessChecker implements AccessEntityCREDChecker
{
    use DefaultAccessCheckerDependency;

    public function __construct(
        /** @phpstan-ignore-next-line property.onlyWritten */
        private DefaultAccessChecker $defaultAccessChecker,
        private AclManager $aclManager,
    ) {}

    public function checkCreate(User $user, ScopeData $data): bool
    {
        return $data->getEdit() !== Table::LEVEL_NO;
    }

    public function checkEntityRead(User $user, Entity $entity, ScopeData $data): bool
    {
        $parent = $this->getSubscription($entity);

        if ($parent) {
            return $this->defaultAccessChecker->checkEntityRead($user, $parent, $data);
        }

        return $this->defaultAccessChecker->checkEntityRead($user, $entity, $data);
    }

    public function checkEntityEdit(User $user, Entity $entity, ScopeData $data): bool
    {
        $parent = $this->getSubscription($entity);

        if ($parent) {
            return $this->defaultAccessChecker->checkEntityEdit($user, $parent, $data);
        }

        return $this->defaultAccessChecker->checkEntityEdit($user, $entity, $data);
    }

    public function checkDelete(User $user, ScopeData $data): bool
    {
        return $data->getEdit() !== Table::LEVEL_NO;
    }

    public function checkEntityCreate(User $user, Entity $entity, ScopeData $data): bool
    {
        return $this->aclManager->checkEntityEdit($user, $entity->getSubscription());
    }

    public function checkEntityDelete(User $user, Entity $entity, ScopeData $data): bool
    {
        $parent = $this->getSubscription($entity);

        if ($parent) {
            return $this->defaultAccessChecker->checkEntityEdit($user, $parent, $data);
        }

        return $this->defaultAccessChecker->checkEntityEdit($user, $entity, $data);
    }

    private function getSubscription(SubscriptionUpdate $entity): ?Subscription
    {
        try {
            return $entity->getSubscription();
        } catch (UnexpectedValueException) {}

        return null;
    }
}
