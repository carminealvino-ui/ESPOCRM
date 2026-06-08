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

namespace Espo\Modules\Sales\Classes\ServiceActions\Quote;

use Espo\Core\Currency\ConfigDataProvider;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Modules\Advanced\Tools\Workflow\Action\RunAction\ServiceAction;
use Espo\Modules\Crm\Entities\Opportunity;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Tools\Currency\Conversion\EntityConverterFactory;
use RuntimeException;
use stdClass;

/**
 * @implements ServiceAction<OrderEntity|Opportunity> // @phpstan-ignore generics.notGeneric
 */
class ConvertCurrency implements ServiceAction
{
    public function __construct(
        private InjectableFactory $injectableFactory,
        private Config $config,
        private EntityManager $entityManager
    ) {}

    public function run(Entity $entity, mixed $data): mixed
    {
        if (!class_exists(EntityConverterFactory::class)) {
            throw new RuntimeException("Convert currency service action requires EspoCRM v7.5 or greater.");
        }

        $data ??= (object) [];

        if (!$data instanceof stdClass) {
            throw new RuntimeException('Bad data provided to convertCurrency.');
        }

        $targetCurrency = $data->targetCurrency ?? $this->config->get('defaultCurrency');

        $converter = $this->injectableFactory
            ->create(EntityConverterFactory::class)
            ->create($entity->getEntityType());

        $rates = $this->injectableFactory
            ->create(ConfigDataProvider::class)
            ->getCurrencyRates();

        $converter->convert($entity, $targetCurrency, $rates);

        $this->entityManager->saveEntity($entity, [
            'skipWorkflow' => true,
            'modifiedById' => 'system',
            'addItemList' => true,
            'forceIsNotNew' => true,
        ]);

        return null;
    }
}
