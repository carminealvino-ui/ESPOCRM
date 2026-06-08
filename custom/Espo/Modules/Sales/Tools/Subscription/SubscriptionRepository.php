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
use Espo\Modules\Sales\Entities\Subscription;
use Espo\Modules\Sales\Entities\SubscriptionPeriod as Period;
use Espo\Modules\Sales\Entities\SubscriptionUpdate as Update;
use Espo\ORM\EntityManager;
use Espo\ORM\Name\Attribute as Attr;
use Espo\ORM\Query\Part\Condition as Cond;
use Espo\ORM\Query\Part\Expression as Expr;
use Espo\ORM\Query\Part\WhereItem;
use Espo\ORM\Query\SelectBuilder;
use Traversable;

class SubscriptionRepository
{
    public function __construct(
        private EntityManager $entityManager,
    ) {}

    public function refreshAndLock(Subscription $subscription): void
    {
        $this->entityManager
            ->getRDBRepositoryByClass(Subscription::class)
            ->forUpdate()
            ->sth()
            ->select(Attr::ID)
            ->where([Attr::ID => $subscription->getId()])
            ->findOne();

        $reloaded = $this->entityManager
            ->getRDBRepositoryByClass(Subscription::class)
            ->where([Attr::ID => $subscription->getId()])
            ->findOne();

        if (!$reloaded) {
            return;
        }

        $subscription->setMultiple($reloaded->getValueMap());
        $subscription->setAsFetched();
    }

    /**
     * Actual with missing period on today.
     *
     * @param Date $date Today.
     * @return Traversable<int, Subscription>
     */
    public function findWithMissingPeriod(Date $date): Traversable
    {
        return $this->entityManager
            ->getRDBRepositoryByClass(Subscription::class)
            ->sth()
            ->where([
                Subscription::FIELD_STATUS => $this->getActualStatuses(),
            ])
            ->where(
                $this->prepareMissingPeriodWhere($date)
            )
            ->find();
    }

    public function hasNoPeriodOnDate(Subscription $subscription, Date $date): bool
    {
        return (bool) $this->entityManager
            ->getRDBRepositoryByClass(Subscription::class)
            ->where([
                Attr::ID => $subscription->getId(),
            ])
            ->where(
                $this->prepareMissingPeriodWhere($date)
            )
            ->findOne();
    }

    /**
     * Actual ending today or before.
     *
     * @param Date $date Today.
     * @return Traversable<int, Subscription>
     */
    public function findActualToEnd(Date $date): Traversable
    {
        return $this->entityManager
            ->getRDBRepositoryByClass(Subscription::class)
            ->sth()
            ->where([
                Subscription::FIELD_STATUS => $this->getActualStatuses(),
                Subscription::FIELD_END_DATE . '<=' => $date->toString()
            ])
            ->find();
    }

    /**
     * Find active without periods.
     *
     * @return Traversable<int, Subscription>
     */
    public function findActiveWithNoPeriods(): Traversable
    {
        return $this->entityManager
            ->getRDBRepositoryByClass(Subscription::class)
            ->sth()
            ->where([
                Subscription::FIELD_STATUS => Subscription::STATUS_ACTIVE,
            ])
            ->where(
                Cond::not(
                    Cond::exists(
                        SelectBuilder::create()
                            ->from(Period::ENTITY_TYPE, 'p')
                            ->select(Attr::ID)
                            ->where(
                                Cond::equal(
                                    Expr::column(lcfirst(Subscription::ENTITY_TYPE) . '.' . Attr::ID),
                                    Expr::column('p.' . Period::ATTR_SUBSCRIPTION_ID)
                                )
                            )
                            ->build()
                    )
                )
            )
            ->find();
    }

    /**
     * Find clear with invoiced periods. To be set to 'due'.
     *
     * @return Traversable<int, Subscription>
     */
    public function findClearWithInvoicedPeriods(): Traversable
    {
        return $this->entityManager
            ->getRDBRepositoryByClass(Subscription::class)
            ->sth()
            ->where([
                Subscription::FIELD_BILLING_STATE => Subscription::BILLING_STATE_CLEAR,
                Subscription::FIELD_STATUS => $this->getActualStatuses(),
            ])
            ->where(
                $this->getDueWhereItem()
            )
            ->find();
    }

    /**
     * Find non-clear with no invoiced periods. To be set to 'clear'.
     *
     * @return Traversable<int, Subscription>
     */
    public function findNonClearWithNoInvoicedPeriods(): Traversable
    {
        return $this->entityManager
            ->getRDBRepositoryByClass(Subscription::class)
            ->sth()
            ->where([
                Subscription::FIELD_BILLING_STATE . '!=' => Subscription::BILLING_STATE_CLEAR,
            ])
            ->where(
                $this->getIsClearWhereItem()
            )
            ->find();
    }

    public function isSubscriptionClear(string $id): bool
    {
        return (bool) $this->entityManager
            ->getRDBRepositoryByClass(Subscription::class)
            ->sth()
            ->where([
                Attr::ID => $id,
            ])
            ->where(
                $this->getIsClearWhereItem()
            )
            ->findOne();
    }

    public function isSubscriptionDue(string $id): bool
    {
        return (bool) $this->entityManager
            ->getRDBRepositoryByClass(Subscription::class)
            ->sth()
            ->where([
                Attr::ID => $id,
            ])
            ->where(
                $this->getDueWhereItem()
            )
            ->findOne();
    }

    /**
     * @return string[]
     */
    private function getActualStatuses(): array
    {
        return [
            Subscription::STATUS_TRIAL,
            Subscription::STATUS_ACTIVE,
            Subscription::STATUS_PAUSED,
        ];
    }

    private function prepareMissingPeriodWhere(Date $date): WhereItem
    {
        $alias = lcfirst(Subscription::ENTITY_TYPE);

        return Cond::not(
            Cond::exists(
                SelectBuilder::create()
                    ->from(Period::ENTITY_TYPE)
                    ->where(
                        Cond::equal(
                            Expr::column(Period::ATTR_SUBSCRIPTION_ID ),
                            Expr::column($alias . '.' . Attr::ID)
                        )
                    )
                    ->where([
                        Period::FIELD_START_DATE . '<=' => $date->toString(),
                        Period::FIELD_END_DATE . '>' => $date->toString(),
                    ])
                    ->build()
            )
        );
    }

    private function getIsClearWhereItem(): WhereItem
    {
        return Cond::not(
            $this->getDueWhereItem()
        );
    }

    private function getDueWhereItem(): WhereItem
    {
        $alias = lcfirst(Subscription::ENTITY_TYPE);
        $sqAlias = 'p';

        return Cond::or(
            Cond::exists(
                SelectBuilder::create()
                    ->from(Period::ENTITY_TYPE, $sqAlias)
                    ->select(Attr::ID)
                    ->where([
                        Period::FIELD_BILLING_STATUS => Period::BILLING_STATUS_INVOICED,
                        Period::FIELD_STATUS . '!=' => Period::STATUS_CANCELED,
                    ])
                    ->where(
                        Cond::equal(
                            Expr::column($alias . '.' . Attr::ID),
                            Expr::column($sqAlias . '.' . Period::ATTR_SUBSCRIPTION_ID)
                        )
                    )
                    ->build()
            ),
            Cond::exists(
                SelectBuilder::create()
                    ->from(Update::ENTITY_TYPE, $sqAlias)
                    ->select(Attr::ID)
                    ->where([
                        Update::FIELD_BILLING_STATUS => Update::BILLING_STATUS_INVOICED,
                        Update::FIELD_STATUS . '!=' => Update::STATUS_CANCELED,
                    ])
                    ->where(
                        Cond::equal(
                            Expr::column($alias . '.' . Attr::ID),
                            Expr::column($sqAlias . '.' . Update::ATTR_SUBSCRIPTION_ID)
                        )
                    )
                    ->build()
            ),
        );
    }

    public function getNew(): Subscription
    {
        return $this->entityManager->getRDBRepositoryByClass(Subscription::class)->getNew();
    }
}
