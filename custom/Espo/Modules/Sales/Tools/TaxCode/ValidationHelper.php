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

namespace Espo\Modules\Sales\Tools\TaxCode;

use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\Sales\Entities\CreditNoteItem;
use Espo\Modules\Sales\Entities\InvoiceItem;
use Espo\Modules\Sales\Entities\TaxCode;
use Espo\Modules\Sales\Tools\Sales\ConfigDataProvider;
use Espo\ORM\EntityManager;

class ValidationHelper
{
    public function __construct(
        private EntityManager $entityManager,
        private ConfigDataProvider $configDataProvider,
    ) {}

    /**
     * @throws Forbidden
     */
    public function validateDelete(TaxCode $taxCode): void
    {
        $this->checkNotUsedInComposite($taxCode);
        $this->checkNotUsedInIssuedDocument($taxCode);
    }

    /**
     * @throws Forbidden
     */
    private function checkNotUsedInIssuedDocument(TaxCode $taxCode): void
    {
        if (!$this->configDataProvider->isIssuanceLockingEnabled()) {
            return;
        }

        $oneIssuedInvoice = $this->entityManager
            ->getRDBRepositoryByClass(InvoiceItem::class)
            ->leftJoin('invoice')
            ->where([
                'taxCodeId' => $taxCode->getId(),
                'invoice.isIssued' => true,
            ])
            ->findOne();

        if ($oneIssuedInvoice) {
            throw Forbidden::createWithBody(
                'cannotRemoveUsedInIssuedDocument',
                Body::create()->withMessageTranslation('cannotRemoveUsedInIssuedDocument', TaxCode::ENTITY_TYPE)
            );
        }

        $oneIssuedCreditNote = $this->entityManager
            ->getRDBRepositoryByClass(CreditNoteItem::class)
            ->leftJoin('creditNote')
            ->where([
                'taxCodeId' => $taxCode->getId(),
                'creditNote.isIssued' => true,
            ])
            ->findOne();

        if ($oneIssuedCreditNote) {
            throw Forbidden::createWithBody(
                'cannotRemoveUsedInIssuedDocument',
                Body::create()->withMessageTranslation('cannotRemoveUsedInIssuedDocument', TaxCode::ENTITY_TYPE)
            );
        }
    }

    /**
     * @throws Forbidden
     */
    private function checkNotUsedInComposite(TaxCode $taxCode): void
    {
        $oneParent = $this->entityManager
            ->getRelation($taxCode, 'parentItems')
            ->findOne();

        if ($oneParent) {
            throw Forbidden::createWithBody(
                'cannotRemoveUsedInComposite',
                Body::create()->withMessageTranslation('cannotRemoveUsedInComposite', TaxCode::ENTITY_TYPE)
            );
        }
    }
}
