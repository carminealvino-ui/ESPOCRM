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

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Record\Collection;
use Espo\Core\Select\SearchParams;
use Espo\Core\Select\SelectBuilderFactory;
use Espo\Modules\Sales\Entities\Subscription;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Entities\SubscriptionPeriod;
use Espo\Modules\Sales\Entities\SubscriptionUpdate;
use Espo\ORM\EntityManager;
use Espo\ORM\Name\Attribute as Attr;
use Espo\ORM\Query\Part\Condition as Cond;
use Espo\ORM\Query\Part\Expression as Expr;
use Espo\ORM\Query\Part\Join;
use Espo\ORM\Query\SelectBuilder;

class InvoiceRecordService
{
    public function __construct(
        private EntityManager $entityManager,
        private SelectBuilderFactory $selectBuilderFactory,
    ) {}

    /**
     * @return Collection<Invoice>
     * @throws Forbidden
     * @throws BadRequest
     */
    public function find(Subscription $subscription, SearchParams $searchParams): Collection
    {
        $query = $this->selectBuilderFactory
            ->create()
            ->from(Invoice::ENTITY_TYPE)
            ->withSearchParams($searchParams)
            ->buildQueryBuilder()
            ->where(
                Cond::or(
                    Cond::in(
                        Expr::column(Attr::ID),
                        SelectBuilder::create()
                            ->select('m.invoiceId')
                            ->from('InvoiceSubscriptionPeriod', 'm')
                            ->join(
                                Join
                                    ::createWithTableTarget(
                                        SubscriptionPeriod::ENTITY_TYPE,
                                        'p',
                                    )
                                    ->withConditions(
                                        Cond::equal(
                                            Expr::column('m.subscriptionPeriodId'),
                                            Expr::column('p.' . Attr::ID),
                                        )
                                    )
                            )
                            ->where(
                                Cond::equal(
                                    Expr::column('p.' . SubscriptionPeriod::ATTR_SUBSCRIPTION_ID),
                                    $subscription->getId()
                                )
                            )
                            ->where([
                                'p.' . Attr::DELETED => false,
                            ])
                            ->build()
                    ),
                    Cond::in(
                        Expr::column(Attr::ID),
                        SelectBuilder::create()
                            ->select('m.invoiceId')
                            ->from('InvoiceSubscriptionUpdate', 'm')
                            ->join(
                                Join
                                    ::createWithTableTarget(
                                        SubscriptionUpdate::ENTITY_TYPE,
                                        'p',
                                    )
                                    ->withConditions(
                                        Cond::equal(
                                            Expr::column('m.subscriptionUpdateId'),
                                            Expr::column('p.' . Attr::ID),
                                        )
                                    )
                            )
                            ->where(
                                Cond::equal(
                                    Expr::column('p.' . SubscriptionUpdate::ATTR_SUBSCRIPTION_ID),
                                    $subscription->getId()
                                )
                            )
                            ->where([
                                'p.' . Attr::DELETED => false,
                            ])
                            ->build()
                    ),
                )

            )
            ->build();

        $builder = $this->entityManager->getRDBRepositoryByClass(Invoice::class)->clone($query);

        $collection = $builder->find();
        $total = $builder->count();

        return Collection::create($collection, $total);
    }
}
