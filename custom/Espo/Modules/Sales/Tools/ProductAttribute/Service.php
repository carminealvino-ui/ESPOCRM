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

namespace Espo\Modules\Sales\Tools\ProductAttribute;

use Espo\Modules\Sales\Entities\ProductAttributeOption;
use Espo\ORM\EntityManager;
use stdClass;

class Service
{
    public function __construct(
        private EntityManager $entityManager
    ) {}

    /**
     * @param string[] $ids
     * @return stdClass
     * return stdClass<string, object{id: string, name: string}[]>
     */
    public function getOptionsForIds(array $ids): object
    {
        $map = (object) [];

        foreach ($ids as $id) {
            $map->$id = $this->getOptions($id);
        }

        return $map;
    }

    /**
     * @return object{id: string, name: string}[]
     */
    private function getOptions(string $id): array
    {
        $collection = $this->entityManager
            ->getRDBRepositoryByClass(ProductAttributeOption::class)
            ->select(['id', 'name'])
            ->order('order')
            ->where(['attributeId' => $id])
            ->find();

        $list = [];

        foreach ($collection as $entity) {
            $list[] = (object) [
                'id' => $entity->getId(),
                'name' => $entity->getName(),
            ];
        }

        return $list;
    }
}
