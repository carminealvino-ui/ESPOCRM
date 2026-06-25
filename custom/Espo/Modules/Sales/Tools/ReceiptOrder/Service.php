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

namespace Espo\Modules\Sales\Tools\ReceiptOrder;

use Espo\Core\Acl;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\FieldValidation\FieldValidationManager;
use Espo\Core\Record\ActionHistory\Action;
use Espo\Core\Record\ServiceContainer;
use Espo\Modules\Sales\Entities\PurchaseOrder;
use Espo\Modules\Sales\Entities\ReceiptOrder;
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
        private ServiceContainer $serviceContainer
    ) {}

    /**
     * @param string $purchaseOrderId
     * @param stdClass[] $dataList
     * @return Collection<ReceiptOrder>
     * @throws NotFound
     * @throws Forbidden
     * @throws BadRequest
     */
    public function createFromPurchaseOrder(string $purchaseOrderId, array $dataList): Collection
    {
        $purchaseOrder = $this->entityManager
            ->getRDBRepositoryByClass(PurchaseOrder::class)
            ->getById($purchaseOrderId);

        if (!$purchaseOrder) {
            throw new NotFound();
        }

        // @todo Validate already created.

        if (!$this->acl->checkEntityEdit($purchaseOrder)) {
            throw new Forbidden("No edit access to Purchase Order.");
        }

        if (!$this->acl->checkScope(ReceiptOrder::ENTITY_TYPE, Acl\Table::ACTION_CREATE)) {
            throw new Forbidden("No create access for Receipt Order.");
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
            ->getAttributes(ReceiptOrder::ENTITY_TYPE, PurchaseOrder::ENTITY_TYPE, $purchaseOrderId);

        unset($convertData[OrderEntity::ATTR_ITEM_LIST]);
        unset($convertData['name']);

        $receiptOrders = array_map(function ($item) use ($purchaseOrder, $convertData) {
            $entity = $this->entityManager
                ->getRDBRepositoryByClass(ReceiptOrder::class)
                ->getNew();

            $entity->set($convertData);
            $entity->set($item);
            $entity->set('purchaseOrderId', $purchaseOrder->getId());
            $entity->set('supplierId', $purchaseOrder->getSupplier()?->getId());
            $entity->set('accountId', $purchaseOrder->getAccount()?->getId());

            return $entity;
        }, $dataList);

        foreach ($receiptOrders as $order) {
            $this->fieldValidationManager->process($order);
        }

        /** @var EntityCollection<ReceiptOrder> $collection */
        $collection = $this->entityManager
            ->getCollectionFactory()
            ->create(ReceiptOrder::ENTITY_TYPE);

        $this->entityManager
            ->getTransactionManager()
            ->run(function () use ($receiptOrders, $collection) {
                foreach ($receiptOrders as $order) {
                    $this->entityManager->saveEntity($order);

                    $collection[] = $order;
                }
            });

        $service = $this->serviceContainer->getByClass(ReceiptOrder::class);

        foreach ($receiptOrders as $order) {
            $service->processActionHistoryRecord(Action::CREATE, $order);
        }

        return $collection;
    }
}
