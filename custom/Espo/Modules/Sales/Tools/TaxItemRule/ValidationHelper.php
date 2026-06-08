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

namespace Espo\Modules\Sales\Tools\TaxItemRule;

use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\Sales\Entities\TaxClass;
use Espo\Modules\Sales\Entities\TaxItemRule;
use Espo\ORM\EntityManager;

class ValidationHelper
{
    public function __construct(
        private EntityManager $entityManager,
    ) {}

    /**
     * @throws Forbidden
     */
    public function validateDelete(TaxClass $taxClass): void
    {
        $oneProduct = $this->entityManager
            ->getRelation($taxClass, 'products')
            ->findOne();

        if ($oneProduct) {
            throw Forbidden::createWithBody(
                'cannotRemoveReferencedInProduct',
                Body::create()->withMessageTranslation('cannotRemoveReferencedInProduct', TaxClass::ENTITY_TYPE)
            );
        }

        $oneItemRule = $this->entityManager
            ->getRDBRepositoryByClass(TaxItemRule::class)
            ->where(['classId' => $taxClass->getId()])
            ->findOne();

        if ($oneItemRule) {
            throw Forbidden::createWithBody(
                'cannotRemoveReferencedInItemRule',
                Body::create()->withMessageTranslation('cannotRemoveReferencedInItemRule', TaxClass::ENTITY_TYPE)
            );
        }
    }
}
