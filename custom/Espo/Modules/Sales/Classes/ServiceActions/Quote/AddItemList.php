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

namespace Espo\Modules\Sales\Classes\ServiceActions\Quote;

use Espo\Modules\Advanced\Tools\Workflow\Action\RunAction\ServiceAction;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use RuntimeException;
use stdClass;

/**
 * @implements ServiceAction<OrderEntity> // @phpstan-ignore generics.notGeneric
 */
class AddItemList implements ServiceAction
{
    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function run(Entity $entity, mixed $data): mixed
    {
        if (!$data instanceof stdClass) {
            throw new RuntimeException('Bad data provided to addQuoteItemList.');
        }

        if (!isset($data->itemList) || !is_array($data->itemList)) {
            throw new RuntimeException('Bad itemList provided in addQuoteItemList.');
        }

        if (empty($data->itemList)) {
            return null;
        }

        $newItemList = $data->itemList;

        /** @var OrderEntity $entity */
        $entity = $this->entityManager->getEntityById($entity->getEntityType(), $entity->getId());

        if (!$entity->has(OrderEntity::ATTR_ITEM_LIST)) {
            $entity->loadItemListField();
        }

        $itemList = $entity->get(OrderEntity::ATTR_ITEM_LIST);

        foreach ($newItemList as $item) {
            $itemList[] = (object) $item;
        }

        $entity->set(OrderEntity::ATTR_ITEM_LIST, $itemList);

        if (!$entity->has('modifiedById')) {
            $entity->set('modifiedByName', 'System');
        }

        $this->entityManager->saveEntity($entity, [
            'skipWorkflow' => true,
            'modifiedById' => 'system',
            'addItemList' => true,
        ]);

        return null;
    }
}
