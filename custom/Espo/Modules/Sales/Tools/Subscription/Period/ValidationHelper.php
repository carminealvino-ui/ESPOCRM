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

namespace Espo\Modules\Sales\Tools\Subscription\Period;

use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Field\Date;
use Espo\Core\Utils\DateTime;
use Espo\Modules\Sales\Entities\SubscriptionPeriod as Period;
use Espo\ORM\EntityManager;
use Espo\ORM\Name\Attribute;
use Espo\ORM\Query\Part\Condition as Cond;
use Espo\ORM\Query\Part\Expression as Expr;

class ValidationHelper
{
    public function __construct(
        private EntityManager $entityManager,
        private DateTime $dateTimeUtil,
    ) {}

    /**
     * @throws Conflict
     */
    public function validate(Period $period): void
    {
        $this->validateRange($period);
        $this->validateStatus($period);
    }

    /**
     * @throws Conflict
     */
    public function validateRange(Period $period): void
    {
        if (
            !$period->isAttributeChanged(Period::FIELD_START_DATE) &&
            !$period->isAttributeChanged(Period::FIELD_END_DATE)
        ) {
            return;
        }

        $where = [];

        if (!$period->isNew()) {
            $where[Attribute::ID . '!='] = $period->getId();
        }

        $one = $this->entityManager
            ->getRDBRepositoryByClass(Period::class)
            ->select(Attribute::ID)
            ->where([
                Period::ATTR_SUBSCRIPTION_ID => $period->getSubscription()->getId(),
            ])
            ->where(
                Cond::and(
                    Expr::greater(
                        Expr::column(Period::FIELD_END_DATE),
                        $period->getStartDate()->toString(),
                    ),
                    Expr::less(
                        Expr::column(Period::FIELD_START_DATE),
                        $period->getEndDate()->toString(),
                    ),
                )
            )
            ->where($where)
            ->findOne();

        if (!$one) {
            return;
        }

        throw Conflict::createWithBody(
            "Period overlap.",
            Body::create()
                ->withMessageTranslation('periodOverlap', 'Subscription')
                ->encode()
        );
    }

    /**
     * @throws Conflict
     */
    private function validateStatus(Period $period): void
    {
        $toValidate =
            $period->isAttributeChanged(Period::FIELD_START_DATE) ||
            $period->isAttributeChanged(Period::FIELD_END_DATE) ||
            $period->isAttributeChanged(Period::FIELD_STATUS);

        if (!$toValidate) {
            return;
        }

        $today = Date::createToday($this->dateTimeUtil->getTimezone());

        if (
            $period->getStatus() === Period::STATUS_SCHEDULED &&
            $period->getStartDate()->isLessThanOrEqualTo($today)
        ) {
            throw Conflict::createWithBody(
                "Period cannot be scheduled for past.",
                Body::create()
                    ->withMessageTranslation('periodScheduledForPast', 'Subscription')
                    ->encode()
            );
        }

        if (
            $period->getStatus() === Period::STATUS_ACTIVE &&
            $period->getEndDate()->isLessThanOrEqualTo($today)
        ) {
            throw Conflict::createWithBody(
                "Period cannot be active for past.",
                Body::create()
                    ->withMessageTranslation('periodActiveForPast', 'Subscription')
                    ->encode()
            );
        }
    }
}
