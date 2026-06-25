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

namespace Espo\Modules\Sales\Classes\Acl\InventoryNumber;

use Espo\Core\Acl\AccessEntityCREDChecker;
use Espo\Core\Acl\DefaultAccessChecker;
use Espo\Core\Acl\ScopeData;
use Espo\Core\Acl\Traits\DefaultAccessCheckerDependency;
use Espo\Core\AclManager;
use Espo\Entities\User;
use Espo\Modules\Sales\Entities\InventoryNumber;
use Espo\Modules\Sales\Entities\Product;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * @implements AccessEntityCREDChecker<InventoryNumber>
 */
class AccessChecker implements AccessEntityCREDChecker
{
    use DefaultAccessCheckerDependency;

    public function __construct(
        private DefaultAccessChecker $defaultAccessChecker,
        private AclManager $aclManager,
        private EntityManager $entityManager
    ) {}

    public function checkEntityRead(User $user, Entity $entity, ScopeData $data): bool
    {
        if (!$this->defaultAccessChecker->checkEntityRead($user, $entity, $data)) {
            return false;
        }

        if (!$entity->getProduct()) {
            return false;
        }

        $product = $this->entityManager
            ->getRDBRepositoryByClass(Product::class)
            ->getById($entity->getProduct()->getId());

        if (!$product) {
            return false;
        }

        return $this->aclManager->checkEntityRead($user, $product);
    }
}
