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

namespace Espo\Modules\Sales\AppParams;

use Espo\Core\Acl;
use Espo\Core\Utils\Metadata;
use Espo\Modules\Sales\Entities\Quote;
use Espo\Modules\Sales\Entities\Tax;
use Espo\ORM\EntityManager;
use Espo\Tools\App\AppParam;
use stdClass;

class DefaultTaxRateQuote implements AppParam
{
    protected string $entityType = Quote::ENTITY_TYPE;

    public function __construct(
        private EntityManager $entityManager,
        private Acl $acl,
        private Metadata $metadata,
    ) {}

    public function get(): ?stdClass
    {
        if (!$this->acl->checkScope($this->entityType)) {
            return null;
        }

        $taxId = $this->metadata->get("entityDefs.$this->entityType.fields.tax.defaultAttributes.taxId");

        if (!$taxId) {
            return null;
        }

        $tax = $this->entityManager->getRDBRepositoryByClass(Tax::class)->getById($taxId);

        if (!$tax) {
            return null;
        }

        return (object) [
            'rate' => $tax->getRate(),
            'shippingMode' => $tax->getShippingMode(),
        ];
    }
}
