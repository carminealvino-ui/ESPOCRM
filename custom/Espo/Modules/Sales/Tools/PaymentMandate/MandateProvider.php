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

namespace Espo\Modules\Sales\Tools\PaymentMandate;

use Espo\Modules\Crm\Entities\Account;
use Espo\Modules\Sales\Entities\PaymentMandate;
use Espo\ORM\EntityManager;
use Espo\ORM\Query\Part\Order;

class MandateProvider
{
    public function __construct(
        private EntityManager $entityManager,
    ) {}

    public function get(Account $account, string $type): ?PaymentMandate
    {
        return $this->entityManager
            ->getRDBRepositoryByClass(PaymentMandate::class)
            ->where([
                'status' => PaymentMandate::STATUS_ACTIVE,
                'type' => $type,
                'accountId' => $account->getId(),
            ])
            ->order('createdAt', Order::DESC)
            ->findOne();
    }
}
