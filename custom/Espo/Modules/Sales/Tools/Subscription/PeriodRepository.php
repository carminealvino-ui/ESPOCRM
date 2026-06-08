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

namespace Espo\Modules\Sales\Tools\Subscription;

use Espo\Core\Field\Date;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Entities\Subscription;
use Espo\Modules\Sales\Entities\SubscriptionBillingPlan as BillingPlan;
use Espo\Modules\Sales\Entities\SubscriptionPeriod as Period;
use Espo\Modules\Sales\Tools\Invoice\InvoiceStatusProvider;
use Espo\ORM\EntityManager;
use Espo\ORM\Name\Attribute as Attr;
use Espo\ORM\Query\Part\Condition as Cond;
use Espo\ORM\Query\Part\Expression as Expr;
use Espo\ORM\Query\SelectBuilder;
use Traversable;

class PeriodRepository
{
    public function __construct(
        private EntityManager $entityManager,
        private InvoiceStatusProvider $invoiceStatusProvider,
    ) {}

    public function getNew(): Period
    {
        return $this->entityManager->getRDBRepositoryByClass(Period::class)->getNew();
    }

    /**
     * @return Traversable<int, Period>
     */
    public function findActiveEndingBeforeDate(Date $date): Traversable
    {
        return $this->entityManager
            ->getRDBRepositoryByClass(Period::class)
            ->sth()
            ->where([
                Period::FIELD_STATUS => Period::STATUS_ACTIVE,
                Period::FIELD_END_DATE . '<=' => $date->toString(),
            ])
            ->order(Period::FIELD_END_DATE)
            ->find();
    }

    /**
     * @return Traversable<int, Period>
     */
    public function findScheduledEndingBeforeDate(Date $date): Traversable
    {
        return $this->entityManager
            ->getRDBRepositoryByClass(Period::class)
            ->sth()
            ->where([
                Period::FIELD_STATUS => Period::STATUS_SCHEDULED,
                Period::FIELD_END_DATE . '<=' => $date->toString(),
            ])
            ->order(Period::FIELD_END_DATE)
            ->find();
    }

    /**
     * @return Traversable<int, Period>
     */
    public function findScheduledToActivate(Date $date): Traversable
    {
        return $this->entityManager
            ->getRDBRepositoryByClass(Period::class)
            ->sth()
            ->join(Period::LINK_SUBSCRIPTION)
            ->where([
                Period::LINK_SUBSCRIPTION . '.' . Subscription::FIELD_STATUS => $this->getActualSubscriptionStatuses(),
                Period::FIELD_STATUS => Period::STATUS_SCHEDULED,
                Period::FIELD_START_DATE . '<=' => $date->toString(),
            ])
            ->where([
                'OR' => [
                    [
                        Period::FIELD_HOLD_UNTIL_BILLING_COMPLETE => false,
                    ],
                    [
                        Period::FIELD_HOLD_UNTIL_BILLING_COMPLETE => true,
                        Period::FIELD_BILLING_STATUS => Period::BILLING_STATUS_SETTLED,
                    ],
                ],
            ])
            ->order(Period::FIELD_START_DATE)
            ->find();
    }

    /**
     * Last periods with the end date before the specified date.
     *
     * @return Traversable<int, Period>
     */
    public function findEnding(BillingPlan $billingPlan, Date $date): Traversable
    {
        $alias = lcfirst(Period::ENTITY_TYPE);
        $subAlias = 'sub';

        return $this->entityManager
            ->getRDBRepositoryByClass(Period::class)
            ->sth()
            ->join(Period::LINK_SUBSCRIPTION)
            ->where([
                Period::LINK_SUBSCRIPTION . '.' . Subscription::ATTR_BILLING_PLAN_ID => $billingPlan->getId(),
                Period::LINK_SUBSCRIPTION . '.' . Subscription::FIELD_STATUS => $this->getActualSubscriptionStatuses(),
            ])
            ->where(
                Cond::lessOrEqual(
                    Expr::column(Period::FIELD_END_DATE),
                    $date->toString()
                )
            )
            ->where(
                Cond::not(
                    Cond::exists(
                        SelectBuilder::create()
                            ->from(Period::ENTITY_TYPE, $subAlias)
                            ->select(Attr::ID)
                            ->where(
                                Cond::equal(
                                    Expr::column($subAlias . '.' . Period::ATTR_SUBSCRIPTION_ID),
                                    Expr::column($alias . '.' . Period::ATTR_SUBSCRIPTION_ID)
                                )
                            )
                            ->where(
                                Cond::greater(
                                    Expr::column($subAlias . '.' . Period::FIELD_END_DATE),
                                    Expr::column($alias . '.' . Period::FIELD_END_DATE)
                                )
                            )
                            ->build()
                    )
                )
            )
            ->order(Period::FIELD_START_DATE)
            ->find();
    }

    /**
     * Periods eligible for automatic invoice.
     *
     * @param Date $minDate The earliest end date.
     * @return Traversable<int, Period>
     */
    public function findAutomaticInvoicePending(Date $minDate): Traversable
    {
        return $this->entityManager
            ->getRDBRepositoryByClass(Period::class)
            ->sth()
            ->join(Period::LINK_SUBSCRIPTION)
            ->where([
                Period::LINK_SUBSCRIPTION . '.' . Subscription::FIELD_STATUS => [
                    Subscription::STATUS_ACTIVE,
                    Subscription::STATUS_TRIAL,
                    Subscription::STATUS_PAUSED,
                ],
                Period::FIELD_STATUS . '!=' => Period::STATUS_CANCELED, // @todo Revise.
                Period::FIELD_TYPE => Period::TYPE_REGULAR,
                Period::FIELD_BILLING_STATUS => Period::BILLING_STATUS_PENDING,
                Period::FIELD_INVOICE_AUTOMATICALLY => true,
            ])
            ->where(
                Cond::greaterOrEqual(Expr::column(Period::FIELD_END_DATE), $minDate->toString())
            )
            ->order(Period::FIELD_START_DATE)
            ->find();
    }

    /**
     * Invoiced not canceled and clear billing state.
     *
     * @return Traversable<int, Period>
     */
    public function findInvoicedClear(): Traversable
    {
        $linkSubscription = Period::LINK_SUBSCRIPTION;

        return $this->entityManager
            ->getRDBRepositoryByClass(Period::class)
            ->sth()
            ->join($linkSubscription)
            ->where([
                $linkSubscription . '.' . Subscription::FIELD_STATUS => $this->getActualSubscriptionStatuses(),
                $linkSubscription . '.' . Subscription::FIELD_BILLING_STATE => Subscription::BILLING_STATE_CLEAR,
                Period::FIELD_STATUS . '!=' => Period::STATUS_CANCELED,
                Period::FIELD_BILLING_STATUS => Period::BILLING_STATUS_INVOICED,
                Period::FIELD_TYPE => Period::TYPE_REGULAR,
            ])
            ->order(Period::FIELD_START_DATE)
            ->find();
    }

    /**
     * Non-future periods with due invoices.
     *
     * @param Date $today Today.
     * @return Traversable<int, Period>
     */
    public function findWithPastDueInvoices(Date $today): Traversable
    {
        $alias = lcfirst(Period::ENTITY_TYPE);
        $pAlias = 'p';

        return $this->entityManager
            ->getRDBRepositoryByClass(Period::class)
            ->sth()
            ->join(Period::LINK_SUBSCRIPTION)
            ->where([
                Period::LINK_SUBSCRIPTION . '.' . Subscription::FIELD_STATUS => $this->getActualSubscriptionStatuses(),
                Period::FIELD_STATUS . '!=' => Period::STATUS_CANCELED,
                Period::FIELD_BILLING_STATUS => Period::BILLING_STATUS_INVOICED,
                Period::FIELD_TYPE => Period::TYPE_REGULAR,
            ])
            ->where(
                Cond::exists(
                    SelectBuilder::create()
                        ->select(Attr::ID)
                        ->from(Invoice::ENTITY_TYPE)
                        ->join(Invoice::LINK_SUBSCRIPTION_PERIODS, $pAlias)
                        ->where(
                            Cond::equal(
                                Expr::alias($pAlias . '.' . Attr::ID),
                                Expr::alias($alias . '.' . Attr::ID)
                            )
                        )
                        ->where([
                            Invoice::FIELD_STATUS . '!=' => $this->invoiceStatusProvider->getNotOpen(),
                            Invoice::FIELD_DATE_DUE . '<' => $today->toString(),
                        ])
                        ->build()
                )
            )
            ->order(Period::FIELD_START_DATE)
            ->find();
    }

    /**
     * Today's pause but in an active or trial subscription.
     *
     * @param Date $today Today.
     * @return Traversable<int, Period>
     */
    public function findTodaysToPause(Date $today): Traversable
    {
        return $this->entityManager
            ->getRDBRepositoryByClass(Period::class)
            ->sth()
            ->join(Period::LINK_SUBSCRIPTION)
            ->where([
                Period::LINK_SUBSCRIPTION . '.' . Subscription::FIELD_STATUS => [
                    Subscription::STATUS_TRIAL,
                    Subscription::STATUS_ACTIVE,
                ],
                Period::FIELD_STATUS . '!=' => Period::STATUS_CANCELED,
                Period::FIELD_TYPE => Period::TYPE_PAUSE,
            ])
            ->where(
                Cond::and(
                    Expr::lessOrEqual(
                        Expr::column(Period::FIELD_START_DATE),
                        $today->toString()
                    ),
                    Expr::less(
                        $today->toString(),
                        Expr::column(Period::FIELD_END_DATE),
                    ),
                )
            )
            ->order(Period::FIELD_START_DATE)
            ->find();
    }

    /**
     * Today's non-pause but in paused subscription.
     *
     * @param Date $today Today.
     * @return Traversable<int, Period>
     */
    public function findTodaysToUnpause(Date $today): Traversable
    {
        return $this->entityManager
            ->getRDBRepositoryByClass(Period::class)
            ->sth()
            ->join(Period::LINK_SUBSCRIPTION)
            ->where([
                Period::LINK_SUBSCRIPTION . '.' . Subscription::FIELD_STATUS => Subscription::STATUS_PAUSED,
                Period::FIELD_STATUS => Period::STATUS_ACTIVE,
                Period::FIELD_TYPE . '!=' => Period::TYPE_PAUSE,
            ])
            ->where(
                Cond::and(
                    Expr::lessOrEqual(
                        Expr::column(Period::FIELD_START_DATE),
                        $today->toString()
                    ),
                    Expr::less(
                        $today->toString(),
                        Expr::column(Period::FIELD_END_DATE),
                    ),
                )
            )
            ->order(Period::FIELD_START_DATE)
            ->find();
    }

    /**
     * @param Date $date.
     * @return Traversable<int, Period>
     */
    public function findBilledAfterDateForSubscription(string $subscriptionId, Date $date): Traversable
    {
        return $this->entityManager
            ->getRDBRepositoryByClass(Period::class)
            ->where([
                Period::ATTR_SUBSCRIPTION_ID => $subscriptionId,
                Period::FIELD_TYPE => Period::TYPE_REGULAR,
                Period::FIELD_BILLING_STATUS => [
                    Period::BILLING_STATUS_INVOICED,
                    Period::BILLING_STATUS_SETTLED,
                ],
                Period::FIELD_END_DATE . '>' => $date->toString(),
            ])
            ->order(Period::FIELD_END_DATE)
            ->find();
    }

    public function findNextPeriod(Subscription $subscription, Date $date): ?Period
    {
        return $this->entityManager
            ->getRDBRepositoryByClass(Period::class)
            ->where([
                Period::ATTR_SUBSCRIPTION_ID => $subscription->getId(),
                Period::FIELD_START_DATE . '>' => $date->toString(),
            ])
            ->order(Period::FIELD_START_DATE)
            ->findOne();
    }

    /**
     * @return string[]
     */
    private function getActualSubscriptionStatuses(): array
    {
        return [
            Subscription::STATUS_TRIAL,
            Subscription::STATUS_ACTIVE,
            Subscription::STATUS_PAUSED,
        ];
    }
}
