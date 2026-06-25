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

namespace Espo\Modules\Sales\Classes\AclPortal\SubscriptionPeriod;

use Espo\Core\Portal\Acl\OwnershipAccountChecker;
use Espo\Core\Portal\Acl\OwnershipContactChecker;
use Espo\Entities\User;
use Espo\Modules\Sales\Entities\SubscriptionPeriod;
use Espo\ORM\Entity;

/**
 * @implements OwnershipAccountChecker<SubscriptionPeriod>
 * @implements OwnershipContactChecker<SubscriptionPeriod>
 */
class OwnershipChecker implements OwnershipAccountChecker, OwnershipContactChecker
{
    public function checkAccount(User $user, Entity $entity): bool
    {
        $accountId = $entity->getSubscription()->getAccount()?->getId();

        if (!$accountId) {
            return false;
        }

        return in_array(
            $entity->getSubscription()->getAccount()?->getId(),
            $user->getAccounts()->getIdList()
        );
    }

    public function checkContact(User $user, Entity $entity): bool
    {
        $contactId = $entity->getSubscription()->getBillingContact()?->getId();

        if (!$contactId) {
            return false;
        }

        return $contactId === $user->getContact()?->getId();
    }
}
