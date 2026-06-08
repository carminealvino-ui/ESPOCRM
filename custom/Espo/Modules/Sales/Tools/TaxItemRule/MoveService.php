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

namespace Espo\Modules\Sales\Tools\TaxItemRule;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\Core\Select\SearchParams;
use Espo\Core\Select\SelectBuilderFactory;
use Espo\Modules\Sales\Entities\TaxItemRule;
use Espo\ORM\EntityManager;
use Espo\ORM\Name\Attribute;
use Espo\ORM\Query\Part\Order;
use Espo\ORM\Query\SelectBuilder;

class MoveService
{
    public const TYPE_TOP = 'top';
    public const TYPE_BOTTOM = 'bottom';
    public const TYPE_UP = 'up';
    public const TYPE_DOWN = 'down';

    private const ATTR_ORDER = 'order';

    public function __construct(
        private EntityManager $entityManager,
        private SelectBuilderFactory $selectBuilderFactory,
    ) {}

    /**
     * @param self::TYPE_TOP|self::TYPE_BOTTOM|self::TYPE_UP|self::TYPE_DOWN $type
     * @throws BadRequest
     * @throws Forbidden
     */
    public function move(TaxItemRule $rule, string $type, SearchParams $searchParams): void
    {
        $builder = $this->createSelectBuilder($searchParams, $rule);

        if ($type === self::TYPE_TOP) {
            $this->moveToTop($rule, $builder);
        } else if ($type === self::TYPE_BOTTOM) {
            $this->moveToBottom($rule, $builder);
        } else if ($type === self::TYPE_UP) {
            $this->moveUp($rule, $builder);
        } else {
            $this->moveDown($rule, $builder);
        }

        $this->reOrder($rule->getTax()->getId());
    }

    public function reOrder(string $taxId): void
    {
        $this->entityManager
            ->getTransactionManager()
            ->run(fn () => $this->reOrderInternal($taxId));
    }

    private function reOrderInternal(string $taxId): void
    {
        $this->entityManager
            ->getRDBRepositoryByClass(TaxItemRule::class)
            ->forUpdate()
            ->sth()
            ->select(Attribute::ID)
            ->where([
                TaxItemRule::ATTR_TAX_ID => $taxId,
            ])
            ->find();

        $collection = $this->entityManager
            ->getRDBRepositoryByClass(TaxItemRule::class)
            ->sth()
            ->where([
                TaxItemRule::ATTR_TAX_ID => $taxId,
            ])
            ->order(self::ATTR_ORDER)
            ->find();

        foreach ($collection as $i => $entity) {
            $order = $i + 1;

            if ($entity->getOrder() === $order) {
                continue;
            }

            $entity->set(self::ATTR_ORDER, $order);

            $this->entityManager->saveEntity($entity, [SaveOption::SKIP_HOOKS => true]);
        }
    }

    /**
     * @throws BadRequest
     * @throws Forbidden
     */
    private function createSelectBuilder(SearchParams $searchParams, TaxItemRule $rule): SelectBuilder
    {
        /** @noinspection PhpRedundantOptionalArgumentInspection */
        return $this->selectBuilderFactory
            ->create()
            ->from(TaxItemRule::ENTITY_TYPE)
            ->withSearchParams($searchParams)
            ->withStrictAccessControl()
            ->buildQueryBuilder()
            ->where([TaxItemRule::ATTR_TAX_ID => $rule->getTax()->getId()])
            ->limit(null, null)
            ->order([]);
    }

    private function moveUp(TaxItemRule $taxRule, SelectBuilder $builder): void
    {
        $query = $builder
            ->where([self::ATTR_ORDER . '<' => $taxRule->getOrder()])
            ->order(self::ATTR_ORDER, Order::DESC)
            ->build();

        $another = $this->entityManager
            ->getRDBRepositoryByClass(TaxItemRule::class)
            ->clone($query)
            ->findOne();

        if (!$another) {
            return;
        }

        $index = $taxRule->getOrder();

        $taxRule->set(self::ATTR_ORDER, $another->getOrder());
        $another->set(self::ATTR_ORDER, $index);

        $this->entityManager->saveEntity($taxRule);
        $this->entityManager->saveEntity($another);
    }

    private function moveDown(TaxItemRule $taxRule, SelectBuilder $builder): void
    {
        $query = $builder
            ->where([self::ATTR_ORDER . '>' => $taxRule->getOrder()])
            ->order(self::ATTR_ORDER, Order::ASC)
            ->build();

        $another = $this->entityManager
            ->getRDBRepositoryByClass(TaxItemRule::class)
            ->clone($query)
            ->findOne();

        if (!$another) {
            return;
        }

        $index = $taxRule->getOrder();

        $taxRule->set(self::ATTR_ORDER, $another->getOrder());
        $another->set(self::ATTR_ORDER, $index);

        $this->entityManager->saveEntity($taxRule);
        $this->entityManager->saveEntity($another);
    }

    private function moveToTop(TaxItemRule $taxRule, SelectBuilder $builder): void
    {
        $query = $builder
            ->where([self::ATTR_ORDER . '<' => $taxRule->getOrder()])
            ->order(self::ATTR_ORDER, Order::ASC)
            ->build();

        $another = $this->entityManager
            ->getRDBRepositoryByClass(TaxItemRule::class)
            ->clone($query)
            ->findOne();

        if (!$another) {
            return;
        }

        $taxRule->set(self::ATTR_ORDER, $another->getOrder() - 1);

        $this->entityManager->saveEntity($taxRule);
    }

    private function moveToBottom(TaxItemRule $taxRule, SelectBuilder $builder): void
    {
        $query = $builder
            ->where([self::ATTR_ORDER . '>' => $taxRule->getOrder()])
            ->order(self::ATTR_ORDER, Order::DESC)
            ->build();

        $another = $this->entityManager
            ->getRDBRepositoryByClass(TaxItemRule::class)
            ->clone($query)
            ->findOne();

        if (!$another) {
            return;
        }

        $taxRule->set(self::ATTR_ORDER, $another->getOrder() + 1);

        $this->entityManager->saveEntity($taxRule);
    }
}
