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

namespace Espo\Modules\Sales\Classes\FieldLoaders\SalesOrder;

use Espo\Core\FieldProcessing\Loader;
use Espo\Core\FieldProcessing\Loader\Params;
use Espo\Modules\Sales\Entities\DeliveryOrderItem;
use Espo\Modules\Sales\Entities\Product;
use Espo\Modules\Sales\Entities\QuoteItem;
use Espo\Modules\Sales\Entities\SalesOrder;
use Espo\Modules\Sales\Entities\SalesOrderItem;
use Espo\ORM\Defs;
use Espo\ORM\Entity;

/**
 * @implements Loader<SalesOrder>
 */
class IsDeliverableLoader implements Loader
{
    private const ATTR = 'isDeliverable';

    public function __construct(
        private Defs $defs,
    ) {}

    /**
     * @inheritDoc
     */
    public function process(Entity $entity, Params $params): void
    {
        $hasGoods = false;
        $hasNonProduct = false;

        $requireProduct = $this->defs
            ->getEntity(DeliveryOrderItem::ENTITY_TYPE)
            ->getField(QuoteItem::FIELD_PRODUCT)
            ->getParam('required');

        foreach ($entity->getItems() as $item) {
            if ($item->get(SalesOrderItem::FIELD_ITEM_TYPE) === Product::ITEM_TYPE_GOODS) {
                $hasGoods = true;
            }

            if (!$item->get(QuoteItem::ATTR_PRODUCT_ID)) {
                $hasNonProduct = true;
            }
        }

        if ($hasGoods) {
            $entity->set(self::ATTR, true);

            return;
        }

        if (!$requireProduct && $hasNonProduct) {
            $entity->set(self::ATTR, true);

            return;
        }

        $entity->set(self::ATTR, false);
    }
}
