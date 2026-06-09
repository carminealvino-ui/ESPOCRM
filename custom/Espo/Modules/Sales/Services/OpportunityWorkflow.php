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

namespace Espo\Modules\Sales\Services;

use Espo\Core\Acl;
use Espo\Core\AclManager;
use Espo\Core\Currency\ConfigDataProvider;
use Espo\Core\InjectableFactory;
use Espo\Core\ServiceFactory;
use Espo\Core\Utils\Config;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Tools\Currency\Conversion\EntityConverterFactory;

use RuntimeException;

/**
 * For bc.
 */
class OpportunityWorkflow
{
    /** @phpstan-ignore-next-line */
    protected $entityType = 'Opportunity';
    private EntityManager $entityManager;
    private Config $config;
    private ServiceFactory $serviceFactory;
    private AclManager $aclManager;
    private InjectableFactory $injectableFactory;

    public function __construct(
        EntityManager $entityManager,
        Config $config,
        ServiceFactory $serviceFactory,
        AclManager $aclManager,
        InjectableFactory $injectableFactory
    ) {
        $this->entityManager = $entityManager;
        $this->config = $config;
        $this->serviceFactory = $serviceFactory;
        $this->aclManager = $aclManager;
        $this->injectableFactory = $injectableFactory;
    }

    /**
     * @noinspection PhpUnused
     * @noinspection PhpUnusedParameterInspection
     * @phpstan-ignore-next-line
     */
    public function convertCurrency(string $workflowId, Entity $entity, $data): void
    {
        $targetCurrency = $data->targetCurrency ?? $this->config->get('defaultCurrency');

        if (class_exists('Espo\\Tools\\Currency\\Conversion\\EntityConverterFactory')) {
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

            return;
        }

        // Below is for bc.

        $config = $this->config;

        $baseCurrency = $config->get('baseCurrency');
        $rates = (object) ($config->get('currencyRates', []));

        if ($targetCurrency !== $baseCurrency && !property_exists($rates, $targetCurrency)) {
            throw new RuntimeException("Workflow convert currency: targetCurrency rate is not specified.");
        }

        $entityManager = $this->entityType;

        $service = $this->serviceFactory->create($this->entityType);

        $reloadedEntity = $entityManager->getEntity($entity->getEntityType(), $entity->getId());

        if (method_exists($service, 'getConvertCurrencyValues')) {
            $systemUserId = $this->getSystemUserId();

            /** @var ?User $user */
            $user = $this->entityManager->getEntityById(User::ENTITY_TYPE, $systemUserId);

            if (!$user) {
                throw new RuntimeException();
            }

            $acl = new Acl($this->aclManager, $user);

            /** @phpstan-ignore-next-line */
            $service->setAcl($acl);
            /** @phpstan-ignore-next-line */
            $service->setUser($user);

            $values = $service->getConvertCurrencyValues($reloadedEntity, $targetCurrency, $baseCurrency, $rates, true);
            $reloadedEntity->set($values);

            if (count(get_object_vars($values))) {
                $entityManager->saveEntity($reloadedEntity, [
                    'skipWorkflow' => true,
                    'addItemList' => true,
                    'modifiedById' => $systemUserId,
                ]);
            }
        }
    }

    private function getSystemUserId(): string
    {
        if (class_exists("Espo\\Core\\Utils\\SystemUser")) {
            return $this->injectableFactory
                ->create("Espo\\Core\\Utils\\SystemUser")
                ->getId();
        }

        return 'system';
    }
}
