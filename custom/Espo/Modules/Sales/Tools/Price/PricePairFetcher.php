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

namespace Espo\Modules\Sales\Tools\Price;

use Espo\Core\Api\Request;
use Espo\Core\Exceptions\BadRequest;
use Espo\Modules\Sales\Tools\Product\ProductQuantityPair;
use stdClass;

class PricePairFetcher
{
    /**
     * @return ProductQuantityPair[]
     * @throws BadRequest
     */
    public function fetch(Request $request): array
    {
        $items = $request->getParsedBody()->items ?? null;

        if (!is_array($items)) {
            throw new BadRequest();
        }

        $pairs = [];

        foreach ($items as $item) {
            if (!$item instanceof stdClass) {
                throw new BadRequest();
            }

            $productId = $item->productId ?? null;
            $quantity = $item->quantity ?? 1.0;

            if (!$productId) {
                throw new BadRequest();
            }

            if (!is_float($quantity) && !is_int($quantity)) {
                throw new BadRequest();
            }

            $pairs[] = new ProductQuantityPair($productId, $quantity);
        }

        return $pairs;
    }
}
