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

namespace Espo\Modules\Sales\Tools\DeliveryOrder;

use Espo\Core\Acl;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\FieldValidation\FieldValidationManager;
use Espo\Core\Record\ActionHistory\Action;
use Espo\Core\Record\CreateParams;
use Espo\Core\Record\ServiceContainer;
use Espo\Modules\Sales\Classes\Record\Hooks\DeliveryOrder\BeforeCreateValidation;
use Espo\Modules\Sales\Entities\DeliveryOrder;
use Espo\Modules\Sales\Entities\SalesOrder;
use Espo\Modules\Sales\Tools\Quote\ConvertService;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\ORM\Collection;
use Espo\ORM\EntityCollection;
use Espo\ORM\EntityManager;

use stdClass;

class Service
{
    public function __construct(
        private Acl $acl,
        private EntityManager $entityManager,
        private FieldValidationManager $fieldValidationManager,
        private ConvertService $convertService,
        private ServiceContainer $serviceContainer,
        private BeforeCreateValidation $beforeCreateValidation
    ) {}

    /**
     * @param string $salesOrderId
     * @param stdClass[] $dataList
     * @return Collection<DeliveryOrder>
     * @throws NotFound
     * @throws Forbidden
     * @throws BadRequest
     * @throws Conflict
     */
    public function createFromSalesOrder(string $salesOrderId, array $dataList): Collection
    {
        $salesOrder = $this->entityManager
            ->getRDBRepositoryByClass(SalesOrder::class)
            ->getById($salesOrderId);

        if (!$salesOrder) {
            throw new NotFound();
        }

        if ($salesOrder->isDeliveryCreated()) {
            throw new Forbidden("Already created.");
        }

        if (!$this->acl->checkEntityEdit($salesOrder)) {
            throw new Forbidden("No edit access to Sales Order.");
        }

        if (!$this->acl->checkScope(DeliveryOrder::ENTITY_TYPE, Acl\Table::ACTION_CREATE)) {
            throw new Forbidden("No create access for Delivery Order.");
        }

        $dataList = array_values(array_filter($dataList,
            function ($item) {
                return (bool) ($item->itemList ?? null);
            }
        ));

        if ($dataList === []) {
            throw new BadRequest("Empty.");
        }

        $convertData = $this->convertService
            ->getAttributes(DeliveryOrder::ENTITY_TYPE, SalesOrder::ENTITY_TYPE, $salesOrderId);

        unset($convertData[OrderEntity::ATTR_ITEM_LIST]);
        unset($convertData['name']);

        $deliveryOrders = array_map(function ($item) use ($salesOrder, $convertData) {
            $entity = $this->entityManager
                ->getRDBRepositoryByClass(DeliveryOrder::class)
                ->getNew();

            $entity->set($convertData);
            $entity->set($item);
            /** @noinspection PhpRedundantOptionalArgumentInspection */
            $entity->set('id', null);
            $entity->set('salesOrderId', $salesOrder->getId());
            $entity->set('accountId', $salesOrder->getAccount()?->getId());

            return $entity;
        }, $dataList);

        foreach ($deliveryOrders as $order) {
            $this->fieldValidationManager->process($order);
        }

        foreach ($deliveryOrders as $order) {
            $this->beforeCreateValidation->process($order, CreateParams::create());
        }

        /** @var EntityCollection<DeliveryOrder> $collection */
        $collection = $this->entityManager
            ->getCollectionFactory()
            ->create(DeliveryOrder::ENTITY_TYPE);

        $this->entityManager
            ->getTransactionManager()
            ->run(function () use ($deliveryOrders, $collection) {
                foreach ($deliveryOrders as $order) {
                    $this->entityManager->saveEntity($order, ['api' => true]);

                    $collection[] = $order;
                }
            });

        $service = $this->serviceContainer->getByClass(DeliveryOrder::class);

        foreach ($deliveryOrders as $order) {
            $service->processActionHistoryRecord(Action::CREATE, $order);
            $service->loadAdditionalFields($order);
        }

        return $collection;
    }
}
