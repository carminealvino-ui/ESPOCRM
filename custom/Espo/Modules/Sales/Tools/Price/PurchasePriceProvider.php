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

namespace Espo\Modules\Sales\Tools\Price;

use Espo\Core\Currency\ConfigDataProvider as CurrencyConfigDataProvider;
use Espo\Core\Field\Date;
use Espo\Core\Utils\Config;
use Espo\Modules\Sales\Entities\Product;
use Espo\Modules\Sales\Entities\Supplier;
use Espo\Modules\Sales\Entities\SupplierProductPrice;
use Espo\Modules\Sales\Tools\Sales\ConfigDataProvider;
use Espo\ORM\EntityManager;
use Espo\ORM\Query\Part\Condition as Cond;
use Espo\ORM\Query\Part\Expression as Expr;
use Espo\ORM\Query\Part\Order;
use Espo\ORM\Query\Part\Where\Comparison as Comp;
use Espo\ORM\Query\Part\WhereItem;

use Exception;
use RuntimeException;
use DateTimeZone;

class PurchasePriceProvider
{
    public function __construct(
        private EntityManager $entityManager,
        private Config $config,
        private ConfigDataProvider $configDataProvider,
        private CurrencyConfigDataProvider $currencyConfig,
    ) {}

    public function get(Product $product, float $quantity, ?Supplier $supplier): PricePair
    {
        return $this->getInternal($product, $quantity, $supplier);
    }

    public function getSupplerBase(Product $product, Supplier $supplier): PricePair
    {
        return $this->getInternal($product, 1.0, $supplier, true);
    }

    private function getInternal(
        Product $product,
        float $quantity,
        ?Supplier $supplier,
        bool $onlySupplier = false,
    ): PricePair {

        $prices = $supplier ?
            $this->getFromSupplier($product, $quantity, $supplier) :
            null;

        if ($prices) {
            return $prices;
        }

        if ($onlySupplier) {
            return new PricePair(null, null);
        }

        $templateProduct = $this->getTemplateProduct($product);

        if ($templateProduct && $supplier) {
            $prices = $this->getFromSupplier($templateProduct, $quantity, $supplier);

            if ($prices) {
                return $prices;
            }
        }

        if (!$this->configDataProvider->isProductLevelPricesEnabled()) {
            return new PricePair(null, null);
        }

        $costPrice = $product->getCostPrice();

        if (!$costPrice && $templateProduct) {
            $costPrice = $templateProduct->getCostPrice();
        }

        return new PricePair($costPrice, null);
    }

    private function getProductById(string $id): ?Product
    {
        return $this->entityManager
            ->getRDBRepositoryByClass(Product::class)
            ->getById($id);
    }

    private function getFromSupplier(Product $product, float $quantity, Supplier $supplier): ?PricePair
    {
        $unitPrice = $this->entityManager
            ->getRDBRepositoryByClass(SupplierProductPrice::class)
            ->where(['minQuantity<=' => $quantity])
            ->where(['minQuantity!=' => null])
            ->where(['supplierId' => $supplier->getId()])
            ->where(['productId' => $product->getId()])
            ->where($this->getRangeWhere())
            ->where([
                SupplierProductPrice::FIELD_STATUS => SupplierProductPrice::STATUS_ACTIVE,
                SupplierProductPrice::ATTR_PRICE_CURRENCY => $this->currencyConfig->getCurrencyList(),
            ])
            ->order('priceConverted', Order::ASC)
            ->findOne();

        $basePrice = $this->entityManager
            ->getRDBRepositoryByClass(SupplierProductPrice::class)
            ->where(
                Expr::isNull(Expr::column('minQuantity')),
            )
            ->where(['supplierId' => $supplier->getId()])
            ->where(['productId' => $product->getId()])
            ->where($this->getRangeWhere())
            ->where([
                SupplierProductPrice::FIELD_STATUS => SupplierProductPrice::STATUS_ACTIVE,
                SupplierProductPrice::ATTR_PRICE_CURRENCY => $this->currencyConfig->getCurrencyList(),
            ])
            ->order('priceConverted', Order::DESC)
            ->findOne();

        if (!$unitPrice && !$basePrice) {
            return null;
        }

        if (!$unitPrice) {
            return new PricePair(
                $basePrice->getPrice(),
                null
            );
        }

        return new PricePair(
            $unitPrice->getPrice(),
            null
        );
    }

    private function getRangeWhere(): WhereItem
    {
        /** @noinspection PhpDeprecationInspection */
        /** @phpstan-ignore-next-line function.alreadyNarrowedType */
        $todayString = method_exists($this->getToday(), 'toString') ?
            $this->getToday()->toString() :
            $this->getToday()->getString();

        return Cond::or(
            Cond::and(
                Expr::isNull(Expr::column('dateStart')),
                Expr::isNull(Expr::column('dateEnd')),
            ),
            Cond::and(
                Comp::lessOrEqual(Expr::column('dateStart'), $todayString),
                Comp::greaterOrEqual(Expr::column('dateEnd'), $todayString),
            ),
            Cond::and(
                Expr::isNull(Expr::column('dateStart')),
                Comp::greaterOrEqual(Expr::column('dateEnd'), $todayString),
            ),
            Cond::and(
                Comp::lessOrEqual(Expr::column('dateStart'), $todayString),
                Expr::isNull(Expr::column('dateEnd')),
            ),
        );
    }

    private function getToday(): Date
    {
        /** @var string $timeZone */
        $timeZone = $this->config->get('timeZone') ?? 'UTC';

        try {
            return Date::createToday(new DateTimeZone($timeZone));
        } catch (Exception $e) {
            throw new RuntimeException($e->getMessage());
        }
    }

    private function getTemplateProduct(Product $product): ?Product
    {
        return $product->getType() === Product::TYPE_VARIANT && $product->getTemplate() ?
            $this->getProductById($product->getTemplate()->getId()) :
            null;
    }
}
