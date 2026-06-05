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
use Espo\Modules\Sales\Entities\PriceBook;
use Espo\Modules\Sales\Entities\Product;
use Espo\Modules\Sales\Entities\Supplier;
use Espo\Modules\Sales\Tools\Price\PurchasePriceProvider;
use Espo\Modules\Sales\Tools\Price\PricePair;
use Espo\Modules\Sales\Tools\Price\PriceProvider;
use Espo\Modules\Sales\Tools\Price\Sales\Data;
use Espo\Modules\Sales\Tools\Sales\ConfigDataProvider;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * @implements Loader<Product>
 */
class UnitPriceSelect implements Loader
{
    public function __construct(
        private PriceProvider $priceProvider,
        private PurchasePriceProvider $purchasePriceProvider,
        private EntityManager $entityManager,
        private ConfigDataProvider $configDataProvider,
    ) {}

    public function process(Entity $entity, Params $params): void
    {
        if (!$params->hasInSelect('unitPriceSelect')) {
            return;
        }

        $supplierId = $this->findSupplierId($params);
        $isSupplier = $this->isSupplier($params);

        if ($isSupplier) {
            $supplier = null;

            // Empty is purchase w/o a specific supplier.
            if ($supplierId) {
                $supplier = $this->entityManager->getRDBRepositoryByClass(Supplier::class)->getById($supplierId);
            }

            if (!$supplier) {
                if ($this->configDataProvider->isProductLevelPricesEnabled()) {
                    $this->setCostProductLevel($entity);

                    return;
                }

                $this->setPrice($entity, new PricePair(null, null));

                return;
            }

            $pricePair = $this->purchasePriceProvider->get($entity, 1.0, $supplier);

            $this->setPrice($entity, $pricePair);

            return;
        }

        if (
            !$this->configDataProvider->isPriceBooksEnabled() &&
            $this->configDataProvider->isProductLevelPricesEnabled()
        ) {
            $this->setProductLevel($entity);

            return;
        }

        $interval = $this->findInterval($params);

        if ($this->isSubscription($params) && !$interval) {
            return;
        }

        $priceBook = $this->getPriceBook($params);

        $data = new Data(interval: $interval);

        $pricePair = $this->priceProvider->getBase(
            product: $entity,
            priceBook: $priceBook,
            data: $data,
        );

        if (!$pricePair->getUnit() && $this->configDataProvider->isProductLevelPricesEnabled()) {
            $this->setProductLevel($entity);

            return;
        }

        $this->setPrice($entity, $pricePair);
    }

    private function findInterval(Params $params): ?string
    {
        return $this->findId($params, 'unitPriceInterval_');
    }

    private function findSupplierId(Params $params): ?string
    {
        return $this->findId($params, 'unitPriceSupplier_');
    }

    private function findPriceBookId(Params $params): ?string
    {
        return $this->findId($params, 'unitPricePriceBook_');
    }

    private function findId(Params $params, string $prefix): ?string
    {
        foreach ($params->getSelect() ?? [] as $item) {
            if (str_starts_with($item, $prefix)) {
                return substr($item, strlen($prefix));
            }
        }

        return null;
    }

    private function setProductLevel(Product $entity): void
    {
        $entity->set('unitPriceSelect', $entity->getUnitPrice()?->getAmount());
        $entity->set('unitPriceSelectCurrency', $entity->getUnitPrice()?->getCode());
    }

    private function setCostProductLevel(Product $entity): void
    {
        $entity->set('unitPriceSelect', $entity->getCostPrice()?->getAmount());
        $entity->set('unitPriceSelectCurrency', $entity->getCostPrice()?->getCode());
    }

    private function setPrice(Product $entity, PricePair $pair): void
    {
        $entity->set('unitPriceSelect', $pair->getUnit()?->getAmount());
        $entity->set('unitPriceSelectCurrency', $pair->getUnit()?->getCode());
    }


    private function getPriceBook(Params $params): ?PriceBook
    {
        $priceBookId = $this->findPriceBookId($params);

        if (!$priceBookId) {
            return null;
        }

        return $this->entityManager->getRDBRepositoryByClass(PriceBook::class)->getById($priceBookId);
    }

    private function isSupplier(Params $params): bool
    {
        foreach ($params->getSelect() ?? [] as $item) {
            if (str_starts_with($item, 'unitPriceSupplier_')) {
                return true;
            }
        }

        return false;
    }

    private function isSubscription(Params $params): bool
    {
        foreach ($params->getSelect() ?? [] as $item) {
            if (str_starts_with($item, 'unitPriceInterval_')) {
                return true;
            }
        }

        return false;
    }
}
