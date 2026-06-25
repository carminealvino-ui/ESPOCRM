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

namespace Espo\Modules\Sales\Classes\Record\Hooks\CreditNote;

use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Record\Hook\SaveHook;
use Espo\Modules\Sales\Entities\CreditNote;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Tools\CreditNote\AllocationsValidationHelper;
use Espo\Modules\Sales\Tools\Sales\IssuanceLockingValidationHelper;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * @implements SaveHook<CreditNote>
 */
class BeforeSaveValidation implements SaveHook
{
    private const ATTR_INVOICE_ID = 'invoiceId';
    private const ATTR_AMOUNT_CURRENCY = 'amountCurrency';

    public function __construct(
        private EntityManager $entityManager,
        private AllocationsValidationHelper $allocationsValidationHelper,
        private IssuanceLockingValidationHelper $issuanceLockingValidationHelper,
    ) {}

    public function process(Entity $entity): void
    {
        $this->validateInvoice($entity);
        $this->allocationsValidationHelper->validate($entity);
        $this->issuanceLockingValidationHelper->validate($entity);
    }

    /**
     * @throws Forbidden
     */
    private function validateInvoice(CreditNote $entity): void
    {
        if (
            !$entity->isAttributeChanged(self::ATTR_INVOICE_ID) &&
            !$entity->isAttributeChanged(self::ATTR_AMOUNT_CURRENCY)
        ) {
            return;
        }

        $invoiceId = $entity->get(self::ATTR_INVOICE_ID);

        if (!$invoiceId) {
            return;
        }

        $invoice = $this->entityManager->getRDBRepositoryByClass(Invoice::class)->getById($invoiceId);

        if (!$invoice) {
            return;
        }

        if ($invoice->getAmountCurrency() !== $entity->getAmountCurrency()) {
            throw Forbidden::createWithBody(
                "creditNoteDifferentCurrency",
                Body::create()->withMessageTranslation('creditNoteDifferentCurrency', CreditNote::ENTITY_TYPE)
            );
        }
    }
}
