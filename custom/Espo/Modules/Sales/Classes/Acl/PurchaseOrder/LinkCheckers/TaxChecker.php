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

namespace Espo\Modules\Sales\Classes\Acl\PurchaseOrder\LinkCheckers;

use Espo\Core\Acl\LinkChecker;
use Espo\Core\AclManager;
use Espo\Entities\User;
use Espo\Modules\Sales\Entities\PurchaseOrder;
use Espo\Modules\Sales\Entities\Tax;
use Espo\Modules\Sales\Tools\TaxPurchaseRule\RuleService;
use Espo\ORM\Entity;

/**
 * @implements LinkChecker<PurchaseOrder, Tax>
 */
class TaxChecker implements LinkChecker
{
    public function __construct(
        private AclManager $aclManager,
        private RuleService $ruleService,
    ) {}

    public function check(User $user, Entity $entity, Entity $foreignEntity): bool
    {
        if ($this->aclManager->checkEntityRead($user, $foreignEntity)) {
            return true;
        }

        $account = $entity->getSupplierEntity()?->getAccount() ??
            $entity->getAccountEntity();

        if (!$account) {
            return false;
        }

        $tax = $this->ruleService->get($account);

        if (!$tax) {
            return false;
        }

        if ($foreignEntity->getId() === $tax->getId()) {
            return true;
        }

        return false;
    }
}
