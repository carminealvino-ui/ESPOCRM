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

namespace Espo\Modules\Sales\Tools\Price\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\Sales\Entities\PriceRule;
use Espo\Modules\Sales\Entities\PriceRuleCondition;
use Espo\ORM\EntityManager;

/**
 * @noinspection PhpUnused
 */
class GetPriceRuleConditionList implements Action
{
    private const LIMIT = 1000;

    public function __construct(
        private Acl $acl,
        private EntityManager $entityManager
    ) {}

    public function process(Request $request): Response
    {
        if (!$this->acl->checkScope(PriceRule::ENTITY_TYPE)) {
            throw new Forbidden();
        }

        /** @var iterable<PriceRuleCondition> $collection */
        $collection = $this->entityManager
            ->getRDBRepositoryByClass(PriceRuleCondition::class)
            ->order('name')
            ->limit(0, self::LIMIT)
            ->find();

        $list = [];

        foreach ($collection as $entity) {
            $list[] = [
                'id' => $entity->getId(),
                'name' => $entity->getName(),
            ];
        }

        return ResponseComposer::json($list);
    }
}
