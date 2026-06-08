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

namespace Espo\Modules\Sales\Tools\TaxRule;

use Espo\Modules\Crm\Entities\Account;
use Espo\Modules\Sales\Entities\Tax;
use Espo\Modules\Sales\Entities\TaxRule;
use Espo\ORM\EntityManager;
use Espo\Tools\DynamicLogic\ConditionChecker;
use Espo\Tools\DynamicLogic\ConditionCheckerFactory;
use Espo\Tools\DynamicLogic\Exceptions\BadCondition;
use RuntimeException;

class RuleService
{
    private const LIMIT = 200;

    public function __construct(
        private EntityManager $entityManager,
        private ConditionCheckerFactory $checkerFactory,
    ) {}

    public function get(Account $account): ?Tax
    {
        $rules = $this->entityManager
            ->getRDBRepositoryByClass(TaxRule::class)
            ->where([
                'status' => TaxRule::STATUS_ACTIVE,
            ])
            ->order('order')
            ->limit(0, self::LIMIT)
            ->sth()
            ->find();

        $checker = $this->checkerFactory->create($account);

        foreach ($rules as $rule) {
            if ($this->checkRule($checker, $rule)) {
                return $rule->getTax();
            }
        }

        return null;
    }

    private function checkRule(ConditionChecker $checker, TaxRule $rule): bool
    {
        if (!$rule->getLogicItem()) {
            return true;
        }

        try {
            return $checker->check($rule->getLogicItem());
        } catch (BadCondition $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }
    }
}
