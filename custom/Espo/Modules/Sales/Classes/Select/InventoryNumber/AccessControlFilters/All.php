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

namespace Espo\Modules\Sales\Classes\Select\InventoryNumber\AccessControlFilters;

use Espo\Core\Acl\Table;
use Espo\Core\AclManager;
use Espo\Core\Select\AccessControl\Filter;
use Espo\Entities\Team;
use Espo\Entities\User;
use Espo\Modules\Sales\Entities\Product;
use Espo\ORM\Query\Part\Condition as Cond;
use Espo\ORM\Query\SelectBuilder;
use Espo\ORM\Query\SelectBuilder as QueryBuilder;

class All implements Filter
{
    public function __construct(
        private User $user,
        private AclManager $aclManager
    ) {}

    public function apply(QueryBuilder $queryBuilder): void
    {
        $level = $this->aclManager->getLevel($this->user, Product::ENTITY_TYPE, Table::ACTION_READ);

        if ($level === Table::LEVEL_NO) {
            $queryBuilder->where(['id' => null]);

            return;
        }

        if ($level === Table::LEVEL_TEAM) {
            $this->applyTeams($queryBuilder);
        }
    }

    private function applyTeams(QueryBuilder $queryBuilder): void
    {
        $subQuery = SelectBuilder::create()
            ->from(Team::RELATIONSHIP_ENTITY_TEAM)
            ->select('entityId')
            ->where([
                'entityType' => Product::ENTITY_TYPE,
                'teamId' => $this->user->getTeamIdList(),
                'deleted' => false,
            ])
            ->build();

        $queryBuilder
            ->where(
                Cond::in(
                    Cond::column('productId'),
                    $subQuery
                )
            );
    }
}
