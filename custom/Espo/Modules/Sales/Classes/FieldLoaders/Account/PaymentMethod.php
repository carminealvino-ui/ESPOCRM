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

namespace Espo\Modules\Sales\Classes\FieldLoaders\Account;

use Espo\Core\Field\LinkMultiple;
use Espo\Core\FieldProcessing\Loader;
use Espo\Core\FieldProcessing\Loader\Params;
use Espo\Modules\Crm\Entities\Account;
use Espo\ORM\Entity;
use RuntimeException;

/**
 * @implements Loader<Account>
 */
class PaymentMethod implements Loader
{
    private const ATTR_PAYMENT_METHOD_ID = 'paymentMethodId';
    private const ATTR_PAYMENT_METHOD_NAME = 'paymentMethodName';
    private const LINK = 'paymentMethods';
    private const COLUMN_PRIMARY = 'primary';

    public function process(Entity $entity, Params $params): void
    {
        if ($params->hasSelect() && !$params->hasInSelect('paymentMethodId')) {
            return;
        }

        $has = false;

        $linkMultiple = $this->getPaymentMethods($entity);

        foreach ($linkMultiple->getList() as $item) {
            if ($item->getColumnValue(self::COLUMN_PRIMARY)) {
                $has = true;

                $map = [
                    self::ATTR_PAYMENT_METHOD_ID => $item->getId(),
                    self::ATTR_PAYMENT_METHOD_NAME => $item->getName(),
                ];

                foreach ($map as $k => $v) {
                    $entity->set($k, $v);
                    $entity->setFetched($k, $v);
                }

                break;
            }
        }

        if ($has) {
            return;
        }

        $map = [
            self::ATTR_PAYMENT_METHOD_ID => null,
            self::ATTR_PAYMENT_METHOD_NAME => null,
        ];

        foreach ($map as $k => $v) {
            $entity->set($k, $v);
            $entity->setFetched($k, $v);
        }
    }

    private function getPaymentMethods(Account $entity): LinkMultiple
    {
        $linkMultiple = $entity->getValueObject(self::LINK);

        if (!$linkMultiple instanceof LinkMultiple) {
            throw new RuntimeException("No paymentMethods link-multiple.");
        }

        return $linkMultiple;
    }
}
