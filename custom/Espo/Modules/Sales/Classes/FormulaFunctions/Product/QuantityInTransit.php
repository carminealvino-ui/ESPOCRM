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

namespace Espo\Modules\Sales\Classes\FormulaFunctions\Product;

use Espo\Core\FieldProcessing\Loader\Params;
use Espo\Core\Formula\EvaluatedArgumentList;
use Espo\Core\Formula\Exceptions\BadArgumentType;
use Espo\Core\Formula\Exceptions\TooFewArguments;
use Espo\Core\Formula\Func;
use Espo\Modules\Sales\Classes\FieldLoaders\Product\QuantityInTransit as QuantityInTransitLoader;
use Espo\Modules\Sales\Entities\Product;
use Espo\ORM\EntityManager;

/**
 * @noinspection PhpUnused
 */
class QuantityInTransit implements Func
{
    public function __construct(
        private EntityManager $entityManager,
        private QuantityInTransitLoader $loader,
    ) {}

    public function process(EvaluatedArgumentList $arguments): float
    {
        if (count($arguments) < 1) {
            throw TooFewArguments::create(1);
        }

        $id = $arguments[0];

        if (!is_string($id)) {
            throw BadArgumentType::create(1, 'string');
        }

        $product = $this->entityManager->getRDBRepositoryByClass(Product::class)->getById($id);

        if (!$product) {
            return 0.0;
        }

        $this->loader->process($product, Params::create());

        return $product->get('quantityInTransit');
    }
}
