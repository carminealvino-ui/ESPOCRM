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

namespace Espo\Modules\Sales\Tools\Price\MassUpdate;

use Espo\Core\Acl;
use Espo\Core\Binding\BindingContainerBuilder;
use Espo\Core\InjectableFactory;
use Espo\Core\MassAction\Data;
use Espo\Core\MassAction\MassAction;
use Espo\Core\MassAction\Params;
use Espo\Core\MassAction\Result;
use Espo\Entities\User;

/**
 * @noinspection PhpUnused
 */
class UpdateProductPrice implements MassAction
{
    public function __construct(
        private InjectableFactory $injectableFactory,
        private Acl $acl,
        private User $user
    ) {}

    public function process(Params $params, Data $data): Result
    {
        $processor = $this->injectableFactory->createWithBinding(
            Processor::class,
            BindingContainerBuilder::create()
                ->bindInstance(Acl::class, $this->acl)
                ->bindInstance(User::class, $this->user)
                ->bindImplementation(PriceSetter::class, ProductPriceSetter::class)
                ->build()
        );

        return $processor->process($params, $data);
    }
}
