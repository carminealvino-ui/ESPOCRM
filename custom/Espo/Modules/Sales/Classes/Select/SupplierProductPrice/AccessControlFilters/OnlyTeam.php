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

namespace Espo\Modules\Sales\Classes\Select\SupplierProductPrice\AccessControlFilters;

use Espo\Core\Select\AccessControl\Filter;
use Espo\Entities\User;
use Espo\Modules\Sales\Entities\Supplier;
use Espo\Modules\Sales\Entities\SupplierProductPrice;
use Espo\ORM\Query\Part\Condition as Cond;
use Espo\ORM\Query\SelectBuilder as QueryBuilder;

class OnlyTeam implements Filter
{
    public function __construct(
        private User $user,
        private OnlyOwn $onlyOwn
    ) {}

    public function apply(QueryBuilder $queryBuilder): void
    {
        if ($this->user->getTeamIdList() === []) {
            $this->onlyOwn->apply($queryBuilder);

            return;
        }

        $subQuery = QueryBuilder::create()
            ->select('id')
            ->from(SupplierProductPrice::ENTITY_TYPE)
            ->join('supplier', 'supplierAccess')
            ->leftJoin('EntityTeam', 'entityTeam', [
                'entityTeam.entityId:' => 'supplierAccess.id',
                'entityTeam.entityType' => Supplier::ENTITY_TYPE,
                'entityTeam.deleted' => false,
            ])
            ->where([
                'OR' => [
                    'entityTeam.teamId:' => $this->user->getTeamIdList(),
                    'supplierAccess.assignedUserId' => $this->user->getId(),
                ]
            ])
            ->build();

        $queryBuilder->where(
            Cond::in(Cond::column('id'), $subQuery)
        );
    }
}
