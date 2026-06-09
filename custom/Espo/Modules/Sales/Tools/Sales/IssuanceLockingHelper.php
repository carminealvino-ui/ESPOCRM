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

use Espo\Core\Currency\CalculatorUtil;
use Espo\Core\Field\Date;
use Espo\Core\Field\DateTime;
use Espo\Core\Name\Field;
use Espo\Core\Utils\FieldUtil;
use Espo\Core\Utils\Metadata;
use Espo\Modules\Sales\Entities\CreditNote;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Entities\SupplierBill;
use Espo\Modules\Sales\Entities\SupplierCredit;
use Espo\Modules\Sales\Entities\WriteOffEntry;
use Espo\Modules\Sales\Entities\PaymentEntry;
use Espo\ORM\Defs;
use RuntimeException;

class IssuanceLockingHelper
{
    public function __construct(
        private Metadata $metadata,
        private FieldUtil $fieldUtil,
        private ConfigDataProvider $configDataProvider,
        private Defs $defs,
        private PostingDateHelper $postingDateHelper,
    ) {}

    /**
     * Force locking cannot be changed via the admin UI. To be used to restrict the admin from changing.
     */
    public function isEnabled(): bool
    {
        return $this->configDataProvider->isIssuanceLockingEnabled();
    }

    public function isChanged(
        WriteOffEntry|CreditNote|Invoice|PaymentEntry|SupplierBill|SupplierCredit $entity,
        bool $allowAllocations = false,
    ): bool {

        $entityType = $entity->getEntityType();

        $fieldList = $this->metadata->get("scopes.$entityType.issuanceLockableFieldList") ?? [];

        $fieldList[] = OrderEntity::FIELD_IS_ISSUED;
        $fieldList[] = OrderEntity::FIELD_WAS_ISSUED;

        foreach ($fieldList as $field) {
            if (
                $field === OrderEntity::ATTR_ITEM_LIST &&
                (
                    $entity instanceof Invoice ||
                    $entity instanceof CreditNote ||
                    $entity instanceof SupplierBill ||
                    $entity instanceof SupplierCredit
                )
            ) {
                if ($entity->isItemListChanged()) {
                    return true;
                }

                continue;
            }

            if (
                !$allowAllocations &&
                $field === CreditNote::ATTR_ALLOCATIONS &&
                (
                    $entity instanceof PaymentEntry ||
                    $entity instanceof WriteOffEntry ||
                    $entity instanceof CreditNote ||
                    $entity instanceof SupplierCredit
                )
            ) {
                if ($entity->isAllocationsChanged()) {
                    return true;
                }

                continue;
            }

            $entityDefs = $this->defs->getEntity($entityType);

            foreach ($this->fieldUtil->getActualAttributeList($entityType, $field) as $attribute) {
                if (!$entityDefs->hasAttribute($attribute)) {
                    continue;
                }

                if ($this->isDecimalAndSame($entity, $attribute, $entityDefs)) {
                    continue;
                }

                if ($entity->isAttributeChanged($attribute)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function toApplyCheck(
        WriteOffEntry|CreditNote|Invoice|PaymentEntry|SupplierBill|SupplierCredit $entity,
    ): bool {

        if (!$this->isEnabled()) {
            return false;
        }

        if ($entity->isNew()) {
            return true;
        }

        return $this->isChanged($entity);
    }

    /**
     * @return string[]
     */
    public function getPreIssueStatusList(string $entityType): array
    {
        return $this->metadata->get("scopes.$entityType.preIssueStatusList") ?? [];
    }

    /**
     * @return string[]
     */
    public function getDoneStatusList(string $entityType): array
    {
        return $this->metadata->get("scopes.$entityType.doneStatusList") ?? [];
    }

    /**
     * @return string[]
     */
    public function getCanceledStatusList(string $entityType): array
    {
        return $this->metadata->get("scopes.$entityType.canceledStatusList") ?? [];
    }

    public function isToBeSetAsIssued(
        WriteOffEntry|CreditNote|Invoice|PaymentEntry|SupplierBill|SupplierCredit $entity,
    ): bool {

        $isCanceled = in_array($entity->getStatus(), $this->getCanceledStatusList($entity->getEntityType()));

        if (
            $isCanceled &&
            !$entity->isIssued() &&
            !$entity->isFetchedAsIssued()
        ) {
            return false;
        }

        return !$this->isPreIssued($entity);
    }

    public function setIsIssued(
        WriteOffEntry|CreditNote|Invoice|PaymentEntry|SupplierBill|SupplierCredit $entity,
    ): void {

        if (
            $entity->isAttributeWritten(OrderEntity::FIELD_IS_ISSUED) ||
            $entity->isAttributeWritten(OrderEntity::FIELD_WAS_ISSUED)
        ) {
            throw new RuntimeException("Cannot write isIssued or wasIssued fields.");
        }

        $isToBeIssued = $this->isToBeSetAsIssued($entity);

        if (!$isToBeIssued && $this->isEnabled() && $entity->isIssued()) {
            return;
        }

        if (
            $isToBeIssued &&
            !$entity->isIssued() &&
            (!$entity->getPostingDate() || $this->postingDateHelper->toSync($entity))
        ) {
            $postingDate = $this->getDocumentDate($entity)?->toString();

            $entity->set(OrderEntity::FIELD_POSTING_DATE, $postingDate);
        }

        $entity->set(OrderEntity::FIELD_IS_ISSUED, $isToBeIssued);

        if ($isToBeIssued) {
            if (!$entity->get(OrderEntity::FIELD_WAS_ISSUED)) {
                $entity->set(OrderEntity::FIELD_ISSUED_AT, DateTime::createNow()->toString());

                $byId = $entity->isNew() ?
                    $entity->get(Field::CREATED_BY . 'Id') :
                    $entity->get(Field::MODIFIED_BY . 'Id');

                $entity->set(OrderEntity::FIELD_ISSUED_BY . 'Id', $byId);
            }

            $entity->set(OrderEntity::FIELD_WAS_ISSUED, true);
        }
    }

    public function wasPreIssued(
        WriteOffEntry|CreditNote|Invoice|PaymentEntry|SupplierBill|SupplierCredit $entity,
    ): bool {

        return $entity->getFetchedStatus() &&
            in_array($entity->getFetchedStatus(), $this->getPreIssueStatusList($entity->getEntityType()));
    }

    public function isPreIssued(
        WriteOffEntry|CreditNote|Invoice|PaymentEntry|SupplierBill|SupplierCredit $entity,
    ): bool {

        return in_array($entity->getStatus(), $this->getPreIssueStatusList($entity->getEntityType()));
    }

    public function isDone(
        WriteOffEntry|CreditNote|Invoice|PaymentEntry|SupplierBill|SupplierCredit $entity,
    ): bool {

        return in_array($entity->getStatus(), $this->getDoneStatusList($entity->getEntityType()));
    }

    public function wasDone(
        WriteOffEntry|CreditNote|Invoice|PaymentEntry|SupplierBill|SupplierCredit $entity,
    ): bool {

        return $entity->getFetchedStatus() &&
            in_array($entity->getFetchedStatus(), $this->getDoneStatusList($entity->getEntityType()));
    }

    private function isDecimalAndSame(
        SupplierBill|SupplierCredit|WriteOffEntry|CreditNote|Invoice|PaymentEntry $entity,
        string $attribute,
        Defs\EntityDefs $entityDefs,
    ): bool {

        $dbType = $entityDefs->getAttribute($attribute)->getParam(Defs\Params\AttributeParam::DB_TYPE);

        if ($dbType !== 'decimal') {
            return false;
        }

        $value = $entity->get($attribute);
        $previousValue = $entity->getFetched($attribute);

        if (
            !is_numeric($value) || !is_numeric($previousValue) ||
            !is_string($value) || !is_string($previousValue)
        ) {
            return false;
        }

        if (CalculatorUtil::compare($value, $previousValue) === 0) {
            return true;
        }

        return false;
    }

    private function getDocumentDate(
        SupplierBill|SupplierCredit|WriteOffEntry|CreditNote|Invoice|PaymentEntry $entity,
    ): ?Date {

        if ($entity instanceof SupplierCredit || $entity instanceof CreditNote) {
            return $entity->getDateIssued();
        } else if ($entity instanceof SupplierBill || $entity instanceof Invoice) {
            return $entity->getDateInvoiced();
        } else if ($entity instanceof WriteOffEntry) {
            return $entity->getDate();
        } else {
            return $entity->getDatePaid();
        }
    }
}
