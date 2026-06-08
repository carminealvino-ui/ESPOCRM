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

namespace Espo\Modules\Sales\Classes\FieldSanitizers\Product;

use Espo\Core\FieldSanitize\Sanitizer;
use Espo\Core\FieldSanitize\Sanitizer\Data;
use Espo\Modules\Sales\Entities\ProductAttributeOption;
use Espo\ORM\EntityManager;
use stdClass;

class Attributes implements Sanitizer
{
    public function __construct(private EntityManager $entityManager)
    {}

    public function sanitize(Data $data, string $field): void
    {
        $attributes = $data->get($field);

        if (!is_array($attributes)) {
            return;
        }

        foreach ($attributes as $attribute) {
            if (!$attribute instanceof stdClass) {
                continue;
            }

            $id = $attribute->id ?? null;

            if (!is_string($id)) {
                continue;
            }

            $options = $attribute->options ?? null;

            if (!is_array($options)) {
                $options = [];
                $attribute->options = [];
            }

            foreach ($options as $option) {
                $id = $option->id ?? null;

                if (!is_string($id)) {
                    continue;
                }

                $option->name = $this->getOptionName($id);
            }
        }


        $data->set($field, $attributes);
    }

    private function getOptionName(string $id): string
    {
        $option = $this->entityManager->getRDBRepositoryByClass(ProductAttributeOption::class)->getById($id);

        if (!$option) {
            return $id;
        }

        return $option->getName();
    }
}
