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

namespace Espo\Modules\Sales\Classes\Record\Hooks\Tax;

use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Record\Hook\SaveHook;
use Espo\Modules\Sales\Entities\Tax;
use Espo\Modules\Sales\Tools\Sales\ConfigDataProvider;
use Espo\Modules\Sales\Tools\Tax\TaxBasis;
use Espo\ORM\Entity;

/**
 * @implements SaveHook<Tax>
 */
class BeforeSave implements SaveHook
{
    public function __construct(
        private ConfigDataProvider $configDataProvider,
    ) {}

    public function process(Entity $entity): void
    {
        $this->validateBasisNew($entity);
        $this->validateBasis($entity);
        $this->validateTaxCode($entity);
    }

    /**
     * @throws Forbidden
     */
    private function validateBasisNew(Tax $entity): void
    {
        if (!$entity->isNew()) {
            return;
        }

        if (
            $this->configDataProvider->isTaxCodesEnabled() &&
            $entity->getBasis() !== TaxBasis::TaxCode
        ) {
            throw new Forbidden("Basis must be set to 'Tax Code'.");
        }

        if (
            !$this->configDataProvider->isTaxCodesEnabled() &&
            $entity->getBasis() !== TaxBasis::Rate
        ) {
            throw new Forbidden("Basis must be set to 'Rate'.");
        }
    }

    /**
     * @throws Forbidden
     */
    private function validateBasis(Tax $entity): void
    {
        if ($entity->isNew() || !$entity->isAttributeChanged(Tax::FIELD_BASIS)) {
            return;
        }

        /** @noinspection PhpLoopNeverIteratesInspection */
        /** @noinspection PhpUnusedLocalVariableInspection */
        foreach ($entity->getItemRules() as $rule) {
            throw Forbidden::createWithBody(
                'cannotChangeBasis',
                Body::create()->withMessageTranslation('cannotChangeBasis', Tax::ENTITY_TYPE)
            );
        }
    }

    /**
     * @throws Forbidden
     */
    private function validateTaxCode(Tax $entity): void
    {
        if (
            $entity->getBasis() !== TaxBasis::TaxCode ||
            !$entity->isAttributeChanged(Tax::ATTR_TAX_CODE_ID)
        ) {
            return;
        }

        $taxCode = $entity->getTaxCode();

        if (!$taxCode) {
            return;
        }

        if (!$taxCode->isActive()) {
            throw Forbidden::createWithBody(
                'cannotUseInactiveTaxCode',
                Body::create()->withMessageTranslation('cannotUseInactiveTaxCode', Tax::ENTITY_TYPE)
            );
        }
    }
}
