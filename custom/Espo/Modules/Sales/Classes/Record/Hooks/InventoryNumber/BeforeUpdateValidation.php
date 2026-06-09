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

namespace Espo\Modules\Sales\Classes\Record\Hooks\InventoryNumber;

use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Record\Hook\UpdateHook;
use Espo\Core\Record\UpdateParams;
use Espo\Modules\Sales\Entities\InventoryNumber;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * @implements UpdateHook<InventoryNumber>
 */
class BeforeUpdateValidation implements UpdateHook
{
    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function process(Entity $entity, UpdateParams $params): void
    {
        $this->validateExisting($entity);
    }

    /**
     * @throws Conflict
     */
    private function validateExisting(InventoryNumber $entity): void
    {
        if (!$entity->isNew() && !$entity->isAttributeChanged('name')) {
            return;
        }

        $existing = $this->entityManager
            ->getRDBRepositoryByClass(InventoryNumber::class)
            ->where([
                'productId' => $entity->getProduct()->getId(),
                'name' => $entity->getName(),
            ])
            ->findOne();

        if (!$existing) {
            return;
        }

        throw Conflict::createWithBody(
            'Inventory number already exists.',
            Body::create()
                ->withMessageTranslation('alreadyExists', InventoryNumber::ENTITY_TYPE)
                ->encode()
        );
    }
}
