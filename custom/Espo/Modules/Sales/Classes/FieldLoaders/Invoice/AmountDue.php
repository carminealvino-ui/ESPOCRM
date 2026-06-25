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

namespace Espo\Modules\Sales\Classes\FieldLoaders\Invoice;

use Espo\Core\Field\Currency;
use Espo\Core\FieldProcessing\Loader;
use Espo\Core\FieldProcessing\Loader\Params;
use Espo\Modules\Sales\Entities\CreditNote;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Entities\PaymentAllocation;
use Espo\Modules\Sales\Entities\SupplierBill;
use Espo\Modules\Sales\Entities\SupplierCredit;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Query\Part\Expression as Expr;
use Espo\ORM\Query\SelectBuilder;

/**
 * @implements Loader<Invoice|CreditNote|SupplierBill|SupplierCredit>
 */
class AmountDue implements Loader
{
    private const FIELD = 'amountDue';

    public function __construct(
        private EntityManager $entityManager,
    ) {}

    public function process(Entity $entity, Params $params): void
    {
        if ($entity->has(self::FIELD) && ($params->hasInSelect(self::FIELD))) {
            // Already applied with the select applier.
            return;
        }

        $total = $entity->getGrandTotalAmount();

        if (!$total) {
            return;
        }

        $value = $this->getAllocatedValue($entity);

        $allocated = Currency::create($value, $total->getCode());

        $due = $total->subtract($allocated);

        if ($entity instanceof CreditNote || $entity instanceof SupplierCredit) {
            $allocatedValue = $this->getSelfAllocatedValue($entity, $total->getCode());

            $due = $due->subtract($allocatedValue);
        }

        $entity->setValueObject(self::FIELD, $due);
    }

    /**
     * @return numeric-string
     */
    private function getAllocatedValue(Invoice|CreditNote|SupplierBill|SupplierCredit $entity): string
    {
        if (!$entity->hasId()) {
            return '0.0';
        }

        $query = SelectBuilder::create()
            ->from(PaymentAllocation::ENTITY_TYPE)
            ->select(
                Expr::sum(Expr::column('amount')),
                'sum'
            )
            ->select('targetId')
            ->select('targetType')
            ->group('targetId')
            ->group('targetType')
            ->having([
                'targetId' => $entity->getId(),
                'targetType' => $entity->getEntityType(),
            ])
            ->build();

        $sth = $this->entityManager->getQueryExecutor()->execute($query);

        $row = $sth->fetch();

        if ($row) {
            /** @var numeric-string $value */
            $value = (string) ($row['sum'] ?? '0.0');
        } else {
            $value = '0.0';
        }

        return $value;
    }

    private function getSelfAllocatedValue(CreditNote|SupplierCredit $entity, string $code): Currency
    {
        $value = new Currency(0.0, $code);

        foreach ($entity->getAllocations() as $allocation) {
            $value = $value->add($allocation->getAmount());
        }

        return $value;
    }
}
