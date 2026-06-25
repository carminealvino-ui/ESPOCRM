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

namespace Espo\Modules\Sales\Classes\FieldLoaders\Product;

use Espo\Core\FieldProcessing\Loader;
use Espo\Core\FieldProcessing\Loader\Params;
use Espo\Core\Utils\Metadata;
use Espo\Modules\Sales\Entities\Product;
use Espo\Modules\Sales\Entities\PurchaseOrder;
use Espo\Modules\Sales\Entities\PurchaseOrderItem;
use Espo\Modules\Sales\Entities\ReceiptOrderItem;
use Espo\Modules\Sales\Tools\Sales\ConfigDataProvider;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Query\Part\Expression as Expr;
use Espo\ORM\Query\SelectBuilder;

/**
 * @implements Loader<Product>
 * @noinspection PhpUnused
 */
class QuantityOnOrder implements Loader
{
    public function __construct(
        private Metadata $metadata,
        private ConfigDataProvider $configDataProvider,
        private EntityManager $entityManager
    ) {}

    public function process(Entity $entity, Params $params): void
    {
        if (!$this->configDataProvider->isInventoryTransactionsEnabled()) {
            return;
        }

        if ($params->hasSelect() && !$params->hasInSelect('quantityOnOrder')) {
            return;
        }

        $queryBuilder = SelectBuilder::create()
            ->from(PurchaseOrderItem::ENTITY_TYPE)
            ->select(
                Expr::coalesce(
                    Expr::sum(Expr::column('quantity')),
                    Expr::value('0.0')
                ),
                'sum'
            )
            ->join('purchaseOrder', 'purchaseOrder')
            ->where([
                'purchaseOrder.status' => PurchaseOrder::STATUS_RELEASED,
            ]);

        if ($entity->getType() === Product::TYPE_TEMPLATE) {
            $queryBuilder
                ->join('product')
                ->where(['product.templateId' => $entity->getId()]);
        }
        else {
            $queryBuilder->where(['productId' => $entity->getId()]);
        }

        $query = $queryBuilder->build();

        $sth = $this->entityManager->getQueryExecutor()->execute($query);

        if (!$row = $sth->fetch()) {
            return;
        }

        $quantity = (float) $row['sum'];

        $entity->set('quantityOnOrder', $quantity);

        if ($quantity === 0.0) {
            return;
        }

        $queryMinus = SelectBuilder::create()
            ->from(ReceiptOrderItem::ENTITY_TYPE)
            ->select(
                Expr::coalesce(
                    Expr::sum(Expr::column('quantityReceived')),
                    Expr::value('0.0')
                ),
                'sum'
            )
            ->join('receiptOrder')
            ->join(PurchaseOrder::ENTITY_TYPE, 'po', [
                'po.id:' => 'receiptOrder.purchaseOrderId',
                'po.deleted' => false,
            ])
            ->where([
                'productId' => $entity->getId(),
                'receiptOrder.status' => $this->metadata->get('scopes.ReceiptOrder.doneStatusList', []),
                'po.status' => PurchaseOrder::STATUS_RELEASED,
            ])
            ->build();

        $sth = $this->entityManager->getQueryExecutor()->execute($queryMinus);

        if (!$row = $sth->fetch()) {
            return;
        }

        $quantityMinus = (float) $row['sum'];

        $quantity = max($quantity - $quantityMinus, 0.0);

        $entity->set('quantityOnOrder', $quantity);
    }
}
