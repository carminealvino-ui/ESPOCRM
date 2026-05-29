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

namespace Espo\Modules\Sales\Tools\Tax;

use Espo\Modules\Sales\Entities\Product;
use Espo\Modules\Sales\Entities\Tax;
use Espo\Modules\Sales\Entities\TaxItemRule;
use Espo\Modules\Sales\Tools\Sales\ConfigDataProvider;
use Espo\ORM\EntityCollection;
use Espo\ORM\EntityManager;
use Espo\ORM\Query\Part\Condition;
use Espo\ORM\Query\Part\Expression;
use Espo\ORM\Query\SelectBuilder;
use Espo\ORM\SthCollection;
use LogicException;
use Traversable;

class ProductRateService
{
    public function __construct(
        private EntityManager $entityManager,
        private ConfigDataProvider $configDataProvider,
    ) {}

    public function getProductTax(Tax $tax, Product $product): ?ProductTax
    {
        $rates = $this->getProductTaxes($tax, [$product->getId()]);

        foreach ($rates as $rate) {
            if ($rate->productId === $product->getId()) {
                return $rate;
            }
        }

        return null;
    }

    /**
     * @param string[] $productIds
     * @return ProductTax[]
     */
    public function getProductTaxes(Tax $tax, array $productIds): array
    {
        $rules = $tax->getItemRules();
        $products = $this->getProducts($productIds, $rules);

        $output = [];

        $appliedProductIds = [];

        $map = [];

        foreach ($products as $product) {
            if ($product->isTaxFree() && !$this->configDataProvider->isTaxCodesEnabled()) {
                $map[$product->getId()] = new ProductTax(
                    productId: $product->getId(),
                    rate: 0.0,
                );

                $appliedProductIds[] = $product->getId();

                continue;
            }

            foreach ($rules as $rule) {
                if (!$product->getTaxClasses()->hasId($rule->getClass()->getId())) {
                    continue;
                }

                $code = $tax->getBasis() === TaxBasis::TaxCode ?
                    $rule->getTaxCodeLink() : null;

                $rate = $tax->getBasis() === TaxBasis::Rate ?
                    $rule->getRate() : null;

                $map[$product->getId()] = new ProductTax(
                    productId: $product->getId(),
                    rate: $rate,
                    taxCode: $code,
                );

                $appliedProductIds[] = $product->getId();

                break;
            }
        }

        foreach (array_diff($productIds, $appliedProductIds) as $productId) {
            $code = $tax->getBasis() === TaxBasis::TaxCode ?
                $tax->getTaxCodeLink() : null;

            $rate = $tax->getBasis() === TaxBasis::Rate ?
                $tax->getRate() : null;

            $map[$productId] = new ProductTax(
                productId: $productId,
                rate: $rate,
                taxCode: $code,
            );
        }

        foreach ($productIds as $productId) {
            if (!array_key_exists($productId, $map)) {
                continue;
            }

            $output[] = $map[$productId];
        }

        return $output;
    }

    /**
     * @param string[] $productIds
     * @param EntityCollection<TaxItemRule> $rules
     * @return Traversable<int, Product>
     */
    private function getProducts(array $productIds, EntityCollection $rules): Traversable
    {
        $classIds = $this->getClassIds($rules);

        $relationName = $this->entityManager
            ->getDefs()
            ->getEntity(Product::ENTITY_TYPE)
            ->getRelation('taxClasses')
            ->getRelationshipName();

        $products = $this->entityManager
            ->getRDBRepositoryByClass(Product::class)
            ->select([
                'id',
                'isTaxFree',
            ])
            ->where(['id' => $productIds])
            ->where(
                Condition::in(
                    Expression::column('id'),
                    SelectBuilder::create()
                        ->from(ucfirst($relationName))
                        ->select('productId')
                        ->where(['taxClassId' => $classIds])
                        ->build()
                )
            )
            ->sth()
            ->find();

        if (!$products instanceof SthCollection) {
            throw new LogicException();
        }

        return $products;
    }

    /**
     * @param EntityCollection<TaxItemRule> $rules
     * @return string[]
     */
    private function getClassIds(EntityCollection $rules): array
    {
        $classIds = array_map(fn (TaxItemRule $it) => $it->getClass()->getId(), iterator_to_array($rules));

        return array_unique($classIds);
    }
}
