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

namespace Espo\Modules\Sales\Repositories;

use Espo\Core\ORM\Repository\Option\SaveOption as CoreSaveOption;
use Espo\Core\Repositories\Database;
use Espo\Modules\Sales\Entities\Subscription;
use Espo\Modules\Sales\Entities\SubscriptionUpdate as Update;
use Espo\Modules\Sales\Tools\Sales\SaveOption;
use Espo\ORM\Entity;
use Espo\ORM\Name\Attribute;
use Throwable;

/**
 * @extends Database<Update>
 * @noinspection PhpUnused
 */
class SubscriptionUpdate extends Database
{

    /**
     * @param Update $entity
     */
    public function save(Entity $entity, array $options = []): void
    {
        $this->entityManager
            ->getTransactionManager()
            ->run(function () use ($entity, $options) {
                $options[SaveOption::VALIDATE_ALL] =
                    !empty($options[CoreSaveOption::API]) ||
                    !empty($options[CoreSaveOption::MASS_UPDATE]) ||
                    !empty($options[CoreSaveOption::IMPORT]);

                $this->lock($entity);

                parent::save($entity, $options);
            });
    }

    private function lock(Update $entity): void
    {
        try {
            $subscriptionId = $entity->getSubscription()->getId();
        } catch (Throwable) {
            return;
        }

        $this->entityManager
            ->getRDBRepositoryByClass(Subscription::class)
            ->select(Attribute::ID)
            ->forUpdate()
            ->where([
                Attribute::ID => $subscriptionId,
            ])
            ->find();
    }
}
