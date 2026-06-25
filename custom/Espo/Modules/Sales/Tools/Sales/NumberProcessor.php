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

namespace Espo\Modules\Sales\Tools\Sales;

use Espo\Core\FieldProcessing\NextNumber\Processor;
use Espo\Core\InjectableFactory;
use Espo\Core\ORM\Entity;
use Espo\Modules\Sales\Entities\CreditNote;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Entities\PaymentEntry;
use Espo\Modules\Sales\Entities\SupplierBill;
use Espo\Modules\Sales\Entities\SupplierCredit;
use Espo\Modules\Sales\Entities\WriteOffEntry;
use Espo\ORM\Defs;
use Espo\ORM\Repository\Option\SaveOptions;

class NumberProcessor
{
    /** @var ?Processor */
    private $processor = null;

    public function __construct(
        private InjectableFactory $injectableFactory,
        private IssuanceLockingHelper $issuanceLockingHelper,
        private Defs $defs,
        private ConfigDataProvider $configDataProvider,
    ) {}

    /**
     * Important. Should be called before `setIsIssued`.
     */
    public function process(OrderEntity|PaymentEntry|WriteOffEntry $order, SaveOptions $options): void
    {
        // Available as of v9.3.0.
        if (!class_exists("Espo\\Core\\FieldProcessing\\NextNumber\\Processor")) {
            return;
        }

        $field = $this->getField($order);

        if (!$this->defaultHookIsSuppressed($order, $field)) {
            return;
        }

        if (
            $order instanceof CreditNote ||
            $order instanceof Invoice ||
            $order instanceof SupplierCredit ||
            $order instanceof SupplierBill ||
            $order instanceof PaymentEntry ||
            $order instanceof WriteOffEntry
        ) {
            if ($order->isNew()) {
                $this->getProcessor()->processField($order, OrderEntity::FIELD_NUMBER_DRAFT_A, $options);
            }

            if (
                $this->configDataProvider->isDraftNumberingEnabled() &&
                (
                    $order->isFetchedAsIssued() ||
                    !$this->issuanceLockingHelper->isToBeSetAsIssued($order) ||
                    $order->getFetched(OrderEntity::FIELD_WAS_ISSUED)
                )
            ) {
                return;
            }

            if (!$this->configDataProvider->isDraftNumberingEnabled() && !$order->isNew()) {
                return;
            }
        } else if (!$order->isNew()) {
            return;
        }

        $this->getProcessor()->processField($order, $field, $options);
    }

    private function getProcessor(): Processor
    {
        $this->processor ??= $this->injectableFactory->create(Processor::class);

        return $this->processor;
    }

    private function getField(Entity $order): string
    {
        $field = OrderEntity::FIELD_NUMBER_A;

        if ($order instanceof Invoice && $order->getType() === Invoice::TYPE_DEBIT_NOTE) {
            $field = Invoice::FIELD_NUMBER_DEBIT_NOTE_A;
        }

        return $field;
    }

    private function defaultHookIsSuppressed(Entity $order, string $field): bool
    {
        return (bool) $this->defs
            ->getEntity($order->getEntityType())
            ->getField($field)
            ->getParam('suppressHook');
    }
}
