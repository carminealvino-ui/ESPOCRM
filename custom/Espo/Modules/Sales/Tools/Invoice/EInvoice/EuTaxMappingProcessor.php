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

namespace Espo\Modules\Sales\Tools\Invoice\EInvoice;

use Espo\Modules\Sales\Entities\EuTaxMapping;
use Espo\Modules\Sales\Entities\Product;
use Espo\Modules\Sales\Entities\TaxCode;
use Espo\ORM\EntityManager;

class EuTaxMappingProcessor
{
    public function __construct(
        private EntityManager $entityManager,
    ) {}

    public function get(TaxCode $taxCode, ?Product $product): ?EuTaxMapping
    {
        $entries = $this->entityManager
            ->getRDBRepositoryByClass(EuTaxMapping::class)
            ->where([
                EuTaxMapping::FIELD_TAX_CODE . 'Id' => $taxCode->getId(),
            ])
            ->order(EuTaxMapping::FIELD_ORDER)
            ->find();

        foreach ($entries as $entry) {
            if ($entry->getTaxClass()) {
                if (
                    $product &&
                    in_array(
                        $entry->getTaxClass()->getId(),
                        $product->getTaxClasses()->getIdList(),
                        true
                    )
                ) {
                    return $entry;
                }

                continue;
            }

            return $entry;
        }

        return null;
    }
}
