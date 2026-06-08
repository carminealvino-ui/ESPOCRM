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

namespace Espo\Modules\Sales\Classes\Acl\TaxAllocationItem;

use Espo\Core\Acl\AccessEntityCREDChecker;
use Espo\Core\Acl\ScopeData;
use Espo\Core\AclManager;
use Espo\Entities\User;
use Espo\Modules\Sales\Entities\PaymentEntry;
use Espo\Modules\Sales\Entities\TaxAllocationItem;
use Espo\ORM\Entity;

/**
 * @implements AccessEntityCREDChecker<TaxAllocationItem>
 */
class AccessChecker implements AccessEntityCREDChecker
{
    public function __construct(
        private AclManager $aclManager,
    ) {}

    public function check(User $user, ScopeData $data): bool
    {
        if ($this->hasAccessToEntryScope($user)) {
            return true;
        }

        return false;
    }

    public function checkRead(User $user, ScopeData $data): bool
    {
        if ($this->hasAccessToEntryScope($user)) {
            return true;
        }

        return false;
    }

    public function checkEntityRead(User $user, Entity $entity, ScopeData $data): bool
    {
        $entry = $entity->getPaymentEntry();

        return $this->aclManager->checkEntityRead($user, $entry);
    }

    private function hasAccessToEntryScope(User $user): bool
    {
        if ($this->aclManager->checkScope($user,  PaymentEntry::ENTITY_TYPE)) {
            return true;
        }

        return false;
    }

    public function checkCreate(User $user, ScopeData $data): bool
    {
        return false;
    }

    public function checkEdit(User $user, ScopeData $data): bool
    {
        return false;
    }

    public function checkDelete(User $user, ScopeData $data): bool
    {
        return false;
    }

    public function checkEntityCreate(User $user, Entity $entity, ScopeData $data): bool
    {
        return false;
    }

    public function checkEntityEdit(User $user, Entity $entity, ScopeData $data): bool
    {
        return false;
    }

    public function checkEntityDelete(User $user, Entity $entity, ScopeData $data): bool
    {
        return false;
    }
}
