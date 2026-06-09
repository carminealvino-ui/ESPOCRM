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

namespace Espo\Modules\Sales\Tools\Product;

use Espo\Core\Acl;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Modules\Sales\Entities\Product;
use Espo\Modules\Sales\Tools\ProductAttribute\OptionItem;
use Espo\ORM\EntityManager;
use Espo\ORM\Name\Attribute;
use Espo\ORM\Query\Part\Condition;
use Espo\ORM\Query\Part\Expression;
use Espo\ORM\Query\Part\Where\AndGroup;
use Espo\ORM\Query\SelectBuilder;

class VariantService
{
    public function __construct(
        private EntityManager $entityManager,
        private Acl $acl,
        private VariantCreator $variantCreator
    ) {}

    /**
     * @throws NotFound
     * @throws Forbidden
     */
    public function generate(string $id): int
    {
        $product = $this->getProduct($id);

        $rows = [];

        foreach ($product->getAttributeItems() as $i => $item) {
            $rows[$i] = [];

            foreach ($item->getOptions() as $option) {
                $rows[$i][] = $option;
            }
        }

        $combs = $this->combine($rows, 0);

        $count = 0;

        $this->entityManager->getTransactionManager()
            ->run(function () use ($product, $combs, &$count) {
                $this->generateInTransaction($product, $combs, $count);
            });

        return $count;
    }

    /**
     * @throws Forbidden
     * @throws NotFound
     */
    private function getProduct(string $id): Product
    {
        $product = $this->entityManager
            ->getRDBRepositoryByClass(Product::class)
            ->getById($id);

        if (!$product) {
            throw new NotFound();
        }

        if (!$this->acl->checkScope(Product::ENTITY_TYPE, Acl\Table::ACTION_CREATE)) {
            throw new Forbidden("No create access.");
        }

        if (!$this->acl->checkEntityEdit($product)) {
            throw new Forbidden("No edit access.");
        }

        if ($product->getType() !== Product::TYPE_TEMPLATE) {
            throw new Forbidden("Not a product template.");
        }

        return $product;
    }

    /**
     * @param OptionItem[][] $rows
     * @return OptionItem[][]
     */
    private function combine(array $rows, int $i): array
    {
        if ($i === count($rows)) {
            return [];
        }

        $row = $rows[$i];

        $combs = [];

        foreach ($row as $option) {
            $itemCombs = $this->combine($rows, $i + 1);

            if (count($itemCombs) === 0) {
                $combs[] = [$option];

                continue;
            }

            foreach ($itemCombs as $comb) {
                $combs[] = array_merge([$option], $comb);
            }
        }

        return $combs;
    }

    /**
     * @param OptionItem[][] $combs
     */
    private function generateInTransaction(Product $product, array $combs, int &$count): void
    {
        $this->entityManager
            ->getRDBRepositoryByClass(Product::class)
            ->forUpdate()
            ->select(Attribute::ID)
            ->where([Attribute::ID => $product->getId()])
            ->findOne();

        foreach ($combs as $i => $comb) {
            $added = $this->generateCombination($product, $comb, $i);

            if ($added) {
                $count ++;
            }
        }
    }

    /**
     * @param OptionItem[] $comb
     */
    private function generateCombination(Product $product, array $comb, int $index): bool
    {
        $andBuilder = AndGroup::createBuilder();

        foreach ($comb as $optionItem) {
            $andBuilder->add(
                Condition::in(
                    Expression::column('id'),
                    SelectBuilder::create()
                        ->from('ProductVariantProductAttributeOption')
                        ->select('productId')
                        ->where([
                            'productAttributeOptionId' => $optionItem->getId(),
                        ])
                        ->build()
                )
            );
        }

        $existing = $this->entityManager
            ->getRDBRepositoryByClass(Product::class)
            ->where([
                'type' => Product::TYPE_VARIANT,
                'templateId' => $product->getId(),
            ])
            ->where($andBuilder->build())
            ->findOne();

        if ($existing) {
            return false;
        }

        $this->variantCreator->create($product, $comb, $index);

        return true;
    }
}
