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

namespace Espo\Modules\Sales\Classes\FieldDuplicators\Quote;

use Espo\Core\Record\Duplicator\FieldDuplicator;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\ORM\Entity;
use stdClass;

/** @noinspection PhpUnused */
class ItemList implements FieldDuplicator
{
    /**
     * @param OrderEntity $entity
     */
    public function duplicate(Entity $entity, string $field): stdClass
    {
        if (!$entity->has($field)) {
            $entity->loadItemListField();
        }

        /** @var ?stdClass[] $itemList */
        $itemList = $entity->get($field);

        if ($itemList === null) {
            return (object) [];
        }

        $parentField = lcfirst($entity->getEntityType());

        $unsetAttributes = [
            'id',
            'deleted',
            'accountId',
            'accountName',
            $parentField . 'Id',
            $parentField . 'Name',
            $parentField . 'Status',
        ];

        foreach ($itemList as $item) {
            foreach ($unsetAttributes as $attribute) {
                unset($item->$attribute);
            }
        }

        return (object) [$field => $itemList];
    }
}
