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

namespace Espo\Modules\Sales\Hooks\TaxRule;

use Espo\Core\Hook\Hook\AfterRemove;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Modules\Sales\Entities\TaxRule;
use Espo\Modules\Sales\Tools\TaxRule\MoveService;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Query\Part\Expression as Expr;
use Espo\ORM\Query\Part\Order as OrderPart;
use Espo\ORM\Query\SelectBuilder;
use Espo\ORM\Repository\Option\RemoveOptions;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * @implements BeforeSave<TaxRule>
 * @implements AfterRemove<TaxRule>
 */
class Order implements BeforeSave, AfterRemove
{
    private const ATTR_ORDER = 'order';

    public function __construct(
        private EntityManager $entityManager,
        private MoveService $moveService,
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if (!$entity->isNew()) {
            return;
        }

        $query = SelectBuilder::create()
            ->from(TaxRule::ENTITY_TYPE)
            ->select(Expr::max(Expr::column(self::ATTR_ORDER)), 'max')
            ->select('id')
            ->group('id')
            ->limit(0, 1)
            ->order(Expr::max(Expr::column(self::ATTR_ORDER)), OrderPart::DESC)
            ->build();

        $sth = $this->entityManager->getQueryExecutor()->execute($query);

        $row = $sth->fetch();

        $order = $row ? $row['max'] : 0;
        $order ++;

        $entity->set(self::ATTR_ORDER, $order);
    }

    public function afterRemove(Entity $entity, RemoveOptions $options): void
    {
        $this->moveService->reOrder($entity::class);
    }
}
