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

namespace Espo\Modules\Sales\Classes\FieldValidators\DeliveryOrderItem\Product;

use Espo\Core\FieldValidation\Validator;
use Espo\Core\FieldValidation\Validator\Data;
use Espo\Core\FieldValidation\Validator\Failure;
use Espo\Modules\Sales\Entities\DeliveryOrderItem;
use Espo\Modules\Sales\Entities\Product;
use Espo\Modules\Sales\Entities\ReceiptOrderItem;
use Espo\Modules\Sales\Entities\TransferOrderItem;
use Espo\ORM\Entity;

/**
 * @implements Validator<DeliveryOrderItem|TransferOrderItem|ReceiptOrderItem>
 */
class IsGoods implements Validator
{
    public function validate(Entity $entity, string $field, Data $data): ?Failure
    {
        $product = $entity->getProductEntity();

        if (!$product) {
            return null;
        }

        if ($product->getItemType() === Product::ITEM_TYPE_GOODS) {
            return null;
        }

        return Failure::create();
    }
}
