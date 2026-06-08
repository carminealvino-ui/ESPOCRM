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

namespace Espo\Modules\Sales\Tools\ProductAttribute;

use Espo\Core\Acl;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\Core\Select\SearchParams;
use Espo\Core\Select\SelectBuilderFactory;
use Espo\Modules\Sales\Entities\ProductAttribute;
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

    public function __construct(
        private EntityManager $entityManager,
        private Acl $acl,
        private SelectBuilderFactory $selectBuilderFactory
    ) {}

    /**
     * @param self::TYPE_TOP|self::TYPE_BOTTOM|self::TYPE_UP|self::TYPE_DOWN $type
     * @throws Forbidden
     * @throws NotFound
     * @throws BadRequest
     */
    public function move(string $id, string $type, SearchParams $searchParams): void
    {
        $entity = $this->entityManager
            ->getRDBRepositoryByClass(ProductAttribute::class)
            ->getById($id);

        if (!$entity) {
            throw new NotFound();
        }

        if (!$this->acl->checkScope(ProductAttribute::ENTITY_TYPE, Acl\Table::ACTION_EDIT)) {
            throw new Forbidden();
        }

        $builder = $this->createSelectBuilder($searchParams);

        if ($type === self::TYPE_TOP) {
            $this->moveToTop($entity, $builder);
        } else if ($type === self::TYPE_BOTTOM) {
            $this->moveToBottom($entity, $builder);
        } else if ($type === self::TYPE_UP) {
            $this->moveUp($entity, $builder);
        } else {
            $this->moveDown($entity, $builder);
        }

        $this->reOrder();
    }

    public function reOrder(): void
    {
        $this->entityManager
            ->getTransactionManager()
            ->run(fn () => $this->reOrderInternal());
    }

    private function reOrderInternal(): void
    {
        $this->entityManager
            ->getRDBRepositoryByClass(ProductAttribute::class)
            ->select(Attribute::ID)
            ->forUpdate()
            ->sth()
            ->find();

        $collection = $this->entityManager
            ->getRDBRepositoryByClass(ProductAttribute::class)
            ->sth()
            ->order('order')
            ->find();

        foreach ($collection as $i => $entity) {
            $order = $i + 1;

            if ($entity->getOrder() === $order) {
                continue;
            }

            $entity->set('order', $order);

            $this->entityManager->saveEntity($entity, [SaveOption::SKIP_HOOKS => true]);
        }
    }

    /**
     * @throws BadRequest
     * @throws Forbidden
     */
    private function createSelectBuilder(SearchParams $searchParams): SelectBuilder
    {
        /** @noinspection PhpRedundantOptionalArgumentInspection */
        return $this->selectBuilderFactory
            ->create()
            ->from(ProductAttribute::ENTITY_TYPE)
            ->withSearchParams($searchParams)
            ->withStrictAccessControl()
            ->buildQueryBuilder()
            ->limit(null, null)
            ->order([]);
    }

    private function moveUp(ProductAttribute $entity, SelectBuilder $builder): void
    {
        $query = $builder
            ->where(['order<' => $entity->getOrder()])
            ->order('order', Order::DESC)
            ->build();

        $anotherEntity = $this->entityManager
            ->getRDBRepositoryByClass(ProductAttribute::class)
            ->clone($query)
            ->findOne();

        if (!$anotherEntity) {
            return;
        }

        $index = $entity->getOrder();

        $entity->set('order', $anotherEntity->getOrder());
        $anotherEntity->set('order', $index);

        $this->entityManager->saveEntity($entity);
        $this->entityManager->saveEntity($anotherEntity);
    }

    private function moveDown(ProductAttribute $entity, SelectBuilder $builder): void
    {
        $query = $builder
            ->where(['order>' => $entity->getOrder()])
            ->order('order', Order::ASC)
            ->build();

        $anotherEntity = $this->entityManager
            ->getRDBRepositoryByClass(ProductAttribute::class)
            ->clone($query)
            ->findOne();

        if (!$anotherEntity) {
            return;
        }

        $index = $entity->getOrder();

        $entity->set('order', $anotherEntity->getOrder());
        $anotherEntity->set('order', $index);

        $this->entityManager->saveEntity($entity);
        $this->entityManager->saveEntity($anotherEntity);
    }

    private function moveToTop(ProductAttribute $entity, SelectBuilder $builder): void
    {
        $query = $builder
            ->where(['order<' => $entity->getOrder()])
            ->order('order', Order::ASC)
            ->build();

        $anotherEntity = $this->entityManager
            ->getRDBRepositoryByClass(ProductAttribute::class)
            ->clone($query)
            ->findOne();

        if (!$anotherEntity) {
            return;
        }

        $entity->set('order', $anotherEntity->getOrder() - 1);

        $this->entityManager->saveEntity($entity);
    }

    private function moveToBottom(ProductAttribute $entity, SelectBuilder $builder): void
    {
        $query = $builder
            ->where(['order>' => $entity->getOrder()])
            ->order('order', Order::DESC)
            ->build();

        $anotherEntity = $this->entityManager
            ->getRDBRepositoryByClass(ProductAttribute::class)
            ->clone($query)
            ->findOne();

        if (!$anotherEntity) {
            return;
        }

        $entity->set('order', $anotherEntity->getOrder() + 1);

        $this->entityManager->saveEntity($entity);
    }
}
