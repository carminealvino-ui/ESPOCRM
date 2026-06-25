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

namespace Espo\Modules\Sales\Hooks\Invoice;

use Espo\Core\Field\LinkMultipleItem;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * @implements BeforeSave<Invoice>
 */
class SetPaymentMethod implements BeforeSave
{
    private const ATTR_PAYMENT_METHOD_ID = 'paymentMethodId';
    private const ATTR_PAYMENT_METHOD_NAME = 'paymentMethodName';
    private const ATTR_PAYMENT_METHODS_IDS = 'paymentMethodsIds';

    private const COLUMN_PRIMARY = 'primary';

    public function __construct() {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        $this->controlPrimary($entity);

        if (!$entity->has(self::ATTR_PAYMENT_METHOD_ID)) {
            return;
        }

        $id = $entity->get(self::ATTR_PAYMENT_METHOD_ID);
        $name = $entity->get(self::ATTR_PAYMENT_METHOD_NAME);

        if (!$id) {
            return;
        }

        $linkMultiple = $entity->getPaymentMethods();

        $item = LinkMultipleItem::create($id, $name)
            ->withColumnValue(self::COLUMN_PRIMARY, true);

        $linkMultiple = $linkMultiple->withAdded($item);

        foreach ($linkMultiple->getList() as $item) {
            if ($item->getColumnValue(self::COLUMN_PRIMARY) && $item->getId() !== $id) {
                $newItem = $item->withColumnValue(self::COLUMN_PRIMARY, false);

                $linkMultiple = $linkMultiple->withAdded($newItem);
            }
        }

        $entity->setPaymentMethods($linkMultiple);
    }

    private function controlPrimary(Invoice $entity): void
    {
        if (!$entity->isAttributeChanged(self::ATTR_PAYMENT_METHODS_IDS)) {
            return;
        }

        $linkMultiple = $entity->getPaymentMethods();

        if ($linkMultiple->getCount() === 0) {
            return;
        }

        $hasPrimary = false;

        foreach ($linkMultiple->getList() as $item) {
            if ($item->getColumnValue(self::COLUMN_PRIMARY)) {
                $hasPrimary = true;

                break;
            }
        }

        if ($hasPrimary) {
            return;
        }

        $item = $linkMultiple->getList()[0];
        $item = $item->withColumnValue(self::COLUMN_PRIMARY, true);

        $linkMultiple = $linkMultiple->withAdded($item);

        $entity->setPaymentMethods($linkMultiple);
    }
}
