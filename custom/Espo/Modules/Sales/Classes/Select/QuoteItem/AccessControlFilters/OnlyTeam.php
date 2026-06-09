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

namespace Espo\Modules\Sales\Classes\Select\QuoteItem\AccessControlFilters;

use Espo\Core\Select\AccessControl\Filter;
use Espo\Entities\User;
use Espo\Modules\Sales\Tools\Sales\OrderEntityUtil;
use Espo\ORM\Query\Part\Condition as Cond;
use Espo\ORM\Query\Part\Expression as Expr;
use Espo\ORM\Query\SelectBuilder;
use Espo\ORM\Query\SelectBuilder as QueryBuilder;

/** @noinspection PhpUnused */
class OnlyTeam implements Filter
{
    public function __construct(
        private string $entityType,
        private User $user
    ) {}

    public function apply(QueryBuilder $queryBuilder): void
    {
        $parentLink = OrderEntityUtil::getItemParentField($this->entityType);
        $parentEntityType = ucfirst($parentLink);
        $parentAlias = $parentLink . 'Access';

        $queryBuilder->join($parentLink, $parentAlias);

        $teamIds = $this->user->getTeamIdList();

        if ($teamIds === []) {
            $queryBuilder->where(
                Cond::equal(
                    Expr::column($parentAlias . '.assignedUserId'),
                    $this->user->getId()
                )
            );

            return;
        }

        $subQuery = SelectBuilder::create()
            ->from($parentEntityType)
            ->select('id')
            ->leftJoin('EntityTeam', 'entityTeam', [
                'entityTeam.entityId:' => 'id',
                'entityTeam.entityType' => $parentEntityType,
                'entityTeam.deleted' => false,
            ])
            ->where([
                'OR' => [
                    'entityTeam.teamId' => $teamIds,
                    'assignedUserId' => $this->user->getId(),
                ],
            ])
            ->build();

        $queryBuilder->where(
            Cond::in(
                Expr::column("$parentAlias.id"),
                $subQuery
            )
        );
    }
}
