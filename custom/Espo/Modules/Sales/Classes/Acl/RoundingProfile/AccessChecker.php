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

namespace Espo\Modules\Sales\Classes\Acl\RoundingProfile;

use Espo\Core\Acl\AccessEntityCREDChecker;
use Espo\Core\Acl\DefaultAccessChecker;
use Espo\Core\Acl\ScopeData;
use Espo\Core\Acl\Traits\DefaultAccessCheckerDependency;
use Espo\Core\AclManager;
use Espo\Entities\User;
use Espo\Modules\Sales\Entities\RoundingProfile;
use Espo\Modules\Sales\Tools\Sales\OrderEntityUtil;
use Espo\ORM\Entity;

/**
 * @implements AccessEntityCREDChecker<RoundingProfile>
 */
class AccessChecker implements AccessEntityCREDChecker
{
    use DefaultAccessCheckerDependency;

    public function __construct(
        /** @phpstan-ignore-next-line */
        private DefaultAccessChecker $defaultAccessChecker,
        private AclManager $aclManager,
    ) {}

    public function check(User $user, ScopeData $data): bool
    {
        if ($this->hasAccessToOrderScope($user)) {
            return true;
        }

        return $this->defaultAccessChecker->check($user, $data);
    }

    public function checkRead(User $user, ScopeData $data): bool
    {
        if ($this->hasAccessToOrderScope($user)) {
            return true;
        }

        return $this->defaultAccessChecker->checkRead($user, $data);
    }

    public function checkEntityRead(User $user, Entity $entity, ScopeData $data): bool
    {
        if ($this->hasAccessToOrderScope($user)) {
            return true;
        }

        return $this->defaultAccessChecker->checkEntityRead($user, $entity, $data);
    }

    private function hasAccessToOrderScope(User $user): bool
    {
        foreach (OrderEntityUtil::getEntityTypesWithRoundingProfile() as $itEntityType) {
            if ($this->aclManager->checkScope($user, $itEntityType)) {
                return true;
            }
        }

        return false;
    }
}
