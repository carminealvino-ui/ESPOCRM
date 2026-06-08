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
use Espo\Modules\Sales\Entities\ReceiptOrder as ReceiptOrderEntity;
use Espo\Modules\Sales\Tools\Sales\SaveOption;
use Espo\ORM\Entity;
use Espo\ORM\Name\Attribute;

/**
 * @extends Database<ReceiptOrderEntity>
 */
class ReceiptOrder extends Database
{
    /**
     * @param ReceiptOrderEntity $entity
     */
    public function save(Entity $entity, array $options = []): void
    {
        if (
            empty($options[CoreSaveOption::API]) &&
            empty($options[CoreSaveOption::MASS_UPDATE]) &&
            empty($options[CoreSaveOption::IMPORT]) &&
            !$entity->isStatusChanged() &&
            !$entity->isItemListChanged()
        ) {
            parent::save($entity, $options);

            return;
        }

        $this->entityManager
            ->getTransactionManager()
            ->run(function () use ($entity, $options) {
                $options[SaveOption::VALIDATE_AVAILABLE_SERIAL_NUMBERS] = true;
                $options[SaveOption::VALIDATE_LOCKED] = true;

                if (
                    !$entity->isNew() &&
                    (
                        $entity->isStatusChanged() ||
                        $entity->isItemListChanged()
                    )
                ) {
                    $this->select(Attribute::ID)
                        ->forUpdate()
                        ->where([Attribute::ID => $entity->getId()])
                        ->find();
                }

                parent::save($entity, $options);
            });
    }
}
