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

namespace Espo\Modules\Sales\Tools\Payment;

use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Error\Body;
use Espo\Modules\Sales\Entities\CreditNote;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Entities\PaymentAllocation;
use Espo\Modules\Sales\Entities\SupplierBill;
use Espo\Modules\Sales\Entities\SupplierCredit;
use Espo\ORM\EntityManager;
use Espo\ORM\Name\Attribute;

class PaymentInvoiceValidationHelper
{
    public function __construct(
        private EntityManager $entityManager,
    ) {}

    /**
     * @throws Conflict
     */
    public function validate(Invoice|CreditNote|SupplierBill|SupplierCredit $order): void
    {
        if ($order->isAttributeChanged('amountCurrency') && $this->hasAllocationsAgainst($order)) {
            throw Conflict::createWithBody(
                "Cannot change currency of invoice with payment allocations.",
                Body::create()->withMessageTranslation('cannotChangeCurrencyIfAllocations', 'Invoice')
            );
        }
    }

    public function hasAllocationsAgainst(Invoice|CreditNote|SupplierBill|SupplierCredit $order): bool
    {
        return (bool) $this->entityManager
            ->getRDBRepositoryByClass(PaymentAllocation::class)
            ->select(Attribute::ID)
            ->where([
                'targetId' => $order->getId(),
                'targetType' => $order->getEntityType(),
            ])
            ->findOne();
    }
}
