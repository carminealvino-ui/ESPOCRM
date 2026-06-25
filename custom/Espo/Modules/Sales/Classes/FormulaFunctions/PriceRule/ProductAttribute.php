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

namespace Espo\Modules\Sales\Classes\FormulaFunctions\PriceRule;

use Espo\Core\Di\InjectableFactoryAware;
use Espo\Core\Di\InjectableFactorySetter;
use Espo\Core\Formula\ArgumentList;
use Espo\Core\Formula\AttributeFetcher;
use Espo\Core\Formula\Exceptions\BadArgumentType;
use Espo\Core\Formula\Exceptions\Error;
use Espo\Core\Formula\Exceptions\TooFewArguments;
use Espo\Core\Formula\Functions\BaseFunction;

class ProductAttribute extends BaseFunction implements InjectableFactoryAware
{
    use InjectableFactorySetter;

    public function process(ArgumentList $args)
    {
        if (count($args) < 1) {
            throw TooFewArguments::create(1);
        }

        $attribute = $this->evaluate($args[0]);

        if (!is_string($attribute)) {
            throw BadArgumentType::create(1, 'string');
        }

        $variables = $this->getVariables();

        if (!isset($variables->__product)) {
            throw new Error("ext\\priceRule\\productAttribute called in a wrong context.");
        }

        $entity = $variables->__product;

        $attributeFetched = $this->injectableFactory->create(AttributeFetcher::class);

        return $attributeFetched->fetch($entity, $attribute);
    }
}
