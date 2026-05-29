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

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Exceptions\ForbiddenSilent as ForbiddenSilent;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\Sales\Entities\CreditNote;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Entities\PaymentEntry;
use Espo\Modules\Sales\Entities\Quote;
use Espo\Modules\Sales\Entities\SupplierBill;
use Espo\Modules\Sales\Entities\SupplierCredit;
use Espo\Modules\Sales\Entities\WriteOffEntry;
use Espo\ORM\Defs;
use Espo\ORM\EntityManager;
use RuntimeException;

class IssuanceLockingValidationHelper
{
    public function __construct(
        private IssuanceLockingHelper $helper,
        private DateUtil $dateUtil,
        private EntityManager $entityManager,
        private ConfigDataProvider $configDataProvider,
        private Defs $defs,
    ) {}

    /**
     * @throws Forbidden
     * @throws BadRequest
     */
    public function validate(
        Invoice|CreditNote|PaymentEntry|WriteOffEntry|SupplierBill|SupplierCredit $entity,
    ): void {

        $this->validateStatus($entity);
        $this->validatePostingDate($entity);

        if (!$this->helper->isEnabled()) {
            return;
        }

        $this->validateUnCancel($entity);
        $this->validateNotUnIssued($entity);
        $this->validateCancelWithNoAllocations($entity);
        $this->validateDate($entity);
        $this->validateAllocations($entity);

        if (!$entity->isIssued()) {
            return;
        }

        $this->validateChange($entity);
        $this->validateZeroAmountDue($entity);
    }

    /**
     * @throws Forbidden
     */
    public function validateRemove(
        WriteOffEntry|CreditNote|Invoice|PaymentEntry|SupplierBill|SupplierCredit $entity,
    ): void {

        if (!$this->helper->isEnabled()) {
            return;
        }

        if ($entity->isIssued()) {
            throw ForbiddenSilent::createWithBody(
                'cannotRemoveIssued',
                Body::create()->withMessageTranslation('cannotRemoveIssued', Quote::ENTITY_TYPE)
            );
        }
    }

    /**
     * @throws Forbidden
     */
    private function validateNotUnIssued(
        WriteOffEntry|CreditNote|Invoice|PaymentEntry|SupplierBill|SupplierCredit $entity,
    ): void {

        $isIssued = $this->isNotPreIssued($entity);
        $wasIssued = $this->wasNotPreIssued($entity);

        // Prevents setting wasIssued to false.
        $wasIssuedAndChanged = !$entity->wasIssued() && $entity->getFetched(OrderEntity::FIELD_WAS_ISSUED);

        if (!$isIssued && $wasIssued || $wasIssuedAndChanged) {
            throw ForbiddenSilent::createWithBody(
                'cannotUnIssue',
                Body::create()->withMessageTranslation('cannotUnIssue', Quote::ENTITY_TYPE)
            );
        }
    }

    /**
     * @throws Forbidden
     */
    private function validateUnCancel(
        WriteOffEntry|CreditNote|Invoice|PaymentEntry|SupplierBill|SupplierCredit $entity,
    ): void {

        if (
            $this->wasCanceled($entity) &&
            !$this->isCanceled($entity) &&
            // If was not issued, should not be possible to issue after cancel.
            !$entity->wasIssued()
        ) {
            throw ForbiddenSilent::createWithBody(
                'cannotUncancel',
                Body::create()->withMessageTranslation('cannotUncancel', Quote::ENTITY_TYPE)
            );
        }
    }

    /**
     * @throws Forbidden
     */
    private function validateChange(
        WriteOffEntry|CreditNote|Invoice|PaymentEntry|SupplierBill|SupplierCredit $entity,
    ): void {

        if (!$this->wasNotPreIssued($entity)) {
            return;
        }

        if ($this->helper->isChanged($entity, true)) {
            throw ForbiddenSilent::createWithBody(
                'cannotChangeIssueLockedFields',
                Body::create()->withMessageTranslation('cannotChangeIssueLockedFields', Quote::ENTITY_TYPE)
            );
        }
    }

    /**
     * @throws Forbidden
     */
    private function validateZeroAmountDue(
        WriteOffEntry|CreditNote|Invoice|PaymentEntry|SupplierBill|SupplierCredit $entity,
    ): void {

        if (
            !$entity instanceof Invoice &&
            !$entity instanceof CreditNote &&
            !$entity instanceof SupplierBill &&
            !$entity instanceof SupplierCredit
        ) {
            return;
        }

        if (!$entity->isAttributeChanged(OrderEntity::FIELD_STATUS)) {
            return;
        }

        $canceled = $this->isCanceled($entity) && !$this->wasCanceled($entity);
        $complete = $this->isDone($entity) && !$this->wasDone($entity);

        if (!$canceled && !$complete) {
            return;
        }

        $amountDue = $entity->getAmountDue();

        if (!$amountDue || $amountDue->getAmount() === 0.0) {
            return;
        }

        $key = $complete ? 'cannotCompleteWithAmountDue' : 'cannotCancelWithAmountDue';

        $messageEntityType = Quote::ENTITY_TYPE;

        if ($entity instanceof CreditNote) {
            $messageEntityType = CreditNote::ENTITY_TYPE;
        }

        if ($entity instanceof SupplierCredit) {
            $messageEntityType = SupplierCredit::ENTITY_TYPE;
        }

        throw ForbiddenSilent::createWithBody(
            $key,
            Body::create()->withMessageTranslation($key, $messageEntityType)
        );
    }

    /**
     * @throws Forbidden
     */
    private function validateCancelWithNoAllocations(
        WriteOffEntry|CreditNote|Invoice|PaymentEntry|SupplierBill|SupplierCredit $entity,
    ): void {

        if ($entity instanceof Invoice || $entity instanceof SupplierBill) {
            return;
        }

        $canceled = $this->isCanceled($entity) && !$this->wasCanceled($entity);

        if (!$canceled) {
            return;
        }

        if ($entity->getAllocations() === []) {
            return;
        }

        throw ForbiddenSilent::createWithBody(
            'cannotCancelWithAllocations',
            Body::create()->withMessageTranslation('cannotCancelWithAllocations', Quote::ENTITY_TYPE)
        );
    }

    /**
     * @throws Forbidden
     */
    private function validateDate(
        WriteOffEntry|CreditNote|Invoice|PaymentEntry|SupplierBill|SupplierCredit $entity,
    ): void {

        $toBeIssuedOrIssued = $this->isIssuedOrToBeSetIssued($entity);

        $setToIssued = $toBeIssuedOrIssued &&
            ($this->helper->wasPreIssued($entity) || $entity->isNew());

        if (!$setToIssued) {
            return;
        }

        $today = $this->dateUtil->getToday();

        if ($entity instanceof WriteOffEntry) {
            $date = $entity->getDate();
        } else if ($entity instanceof PaymentEntry) {
            $date = $entity->getDatePaid();
        } else if ($entity instanceof CreditNote || $entity instanceof SupplierCredit) {
            $date = $entity->getDateIssued();
        } else {
            $date = $entity->getDateInvoiced();
        }

        if (!$date) {
            throw ForbiddenSilent::createWithBody(
                'cannotIssueWithoutDate',
                Body::create()->withMessageTranslation('cannotIssueWithoutDate', Quote::ENTITY_TYPE)
            );
        }

        if ($date->isGreaterThan($today)) {
            throw ForbiddenSilent::createWithBody(
                'cannotIssueWithFutureDate',
                Body::create()->withMessageTranslation('cannotIssueWithFutureDate', Quote::ENTITY_TYPE)
            );
        }
    }

    private function wasCanceled(
        WriteOffEntry|CreditNote|Invoice|PaymentEntry|SupplierBill|SupplierCredit $entity,
    ): bool {

        return $entity->getFetchedStatus() &&
            in_array($entity->getFetchedStatus(), $this->helper->getCanceledStatusList($entity->getEntityType()));
    }

    private function isCanceled(
        WriteOffEntry|CreditNote|Invoice|PaymentEntry|SupplierBill|SupplierCredit $entity,
    ): bool {

        return in_array($entity->getStatus(), $this->helper->getCanceledStatusList($entity->getEntityType()));
    }

    private function wasDone(
        WriteOffEntry|CreditNote|Invoice|PaymentEntry|SupplierBill|SupplierCredit $entity,
    ): bool {

        return $this->helper->wasDone($entity);
    }

    private function isDone(
        WriteOffEntry|CreditNote|Invoice|PaymentEntry|SupplierBill|SupplierCredit $entity,
    ): bool {

        return $this->helper->isDone($entity);
    }

    private function wasNotPreIssued(
        WriteOffEntry|CreditNote|Invoice|PaymentEntry|SupplierBill|SupplierCredit $entity,
    ): bool {

        return $entity->getFetchedStatus() && !$this->helper->wasPreIssued($entity);
    }

    private function isNotPreIssued(
        WriteOffEntry|CreditNote|Invoice|PaymentEntry|SupplierBill|SupplierCredit $entity,
    ): bool {

        return !$this->helper->isPreIssued($entity);
    }

    /**
     * @throws Forbidden
     */
    private function validateAllocations(
        WriteOffEntry|CreditNote|Invoice|PaymentEntry|SupplierBill|SupplierCredit $entity,
    ): void {

        if ($entity instanceof Invoice || $entity instanceof SupplierBill) {
            return;
        }

        if (!$entity->isAllocationsChanged()) {
            return;
        }

        foreach ($entity->getAllocations() as $allocation) {
            $link = $allocation->getTarget();

            $target = $this->entityManager->getEntityById($link->getEntityType(), $link->getId());

            if (!$target) {
                throw ForbiddenSilent::createWithBody(
                    'allocationTargetNotFound',
                    Body::create()->withMessageTranslation('allocationTargetNotFound', PaymentEntry::ENTITY_TYPE)
                );
            }

            if (!$target instanceof IssuableOrder) {
                continue;
            }

            if (!$target->isIssued()) {
                throw ForbiddenSilent::createWithBody(
                    'cannotAllocateToNonIssuedTarget',
                    Body::create()
                        ->withMessageTranslation('cannotAllocateToNonIssuedTarget', PaymentEntry::ENTITY_TYPE)
                );
            }
        }
    }

    private function isIssuedOrToBeSetIssued(
        SupplierBill|SupplierCredit|WriteOffEntry|CreditNote|Invoice|PaymentEntry $entity,
    ): bool {

        return $entity->isIssued() || $this->helper->isToBeSetAsIssued($entity);
    }

    /**
     * @throws BadRequest
     */
    private function validatePostingDate(
        SupplierBill|SupplierCredit|WriteOffEntry|CreditNote|Invoice|PaymentEntry $entity,
    ): void {

        if (
            $entity->isIssued() &&
            (
                (
                    ($entity instanceof SupplierCredit || $entity instanceof SupplierBill) &&
                    $this->configDataProvider->isSupplierBillPostingDateEnabled() &&
                    !$entity->getPostingDate()
                ) ||
                (
                    $entity instanceof PaymentEntry &&
                    $this->configDataProvider->isPaymentEntryPostingDateEnabled() &&
                    !$entity->getPostingDate()
                )
            )
        ) {
            throw BadRequest::createWithBody(
                'postingDateRequired',
                Body::create()->withMessageTranslation('postingDateRequired', Quote::ENTITY_TYPE)
            );
        }
    }

    private function validateStatus(OrderEntity|PaymentEntry|WriteOffEntry $entity): void
    {
        $status = $entity->getStatus();

        $statusList = $this->defs
            ->getEntity($entity->getEntityType())
            ->getField(OrderEntity::FIELD_STATUS)
            ->getParam('options');

        if (!is_array($statusList)) {
            throw new RuntimeException("No status options in {$entity->getEntityType()}.");
        }

        if (!in_array($status, $statusList)) {
            throw new RuntimeException("Not allowed status value.");
        }
    }
}
