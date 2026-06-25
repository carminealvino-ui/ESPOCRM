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

namespace Espo\Modules\Sales\Classes\Select\SubscriptionUpdate\AccessControlFilters;

use Espo\Core\Select\AccessControl\Filter;
use Espo\Entities\User;
use Espo\Modules\Sales\Entities\Subscription;
use Espo\Modules\Sales\Entities\SubscriptionUpdate;
use Espo\ORM\Name\Attribute;
use Espo\ORM\Query\Part\Condition as Cond;
use Espo\ORM\Query\Part\Expression;
use Espo\ORM\Query\SelectBuilder;

/**
 * @noinspection PhpUnused
 */
class OnlyAccount implements Filter
{
    public function __construct(
        private User $user,
    ) {}

    public function apply(SelectBuilder $queryBuilder): void
    {
        $accountIds = $this->user->getAccounts()->getIdList();

        if ($accountIds === []) {
            $queryBuilder->where([Attribute::ID => null]);

            return;
        }

        $queryBuilder->where(
            Cond::in(
                Expression::column(SubscriptionUpdate::ATTR_SUBSCRIPTION_ID),
                SelectBuilder::create()
                    ->from(Subscription::ENTITY_TYPE)
                    ->select(Attribute::ID)
                    ->where([
                        Subscription::LINK_ACCOUNT . 'Id' => $accountIds,
                    ])
                    ->build()
            )
        );
    }
}
