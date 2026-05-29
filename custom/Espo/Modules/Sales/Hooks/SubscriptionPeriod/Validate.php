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

namespace Espo\Modules\Sales\Hooks\SubscriptionPeriod;

use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Hook\Hook\BeforeRemove;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Modules\Sales\Entities\SubscriptionPeriod;
use Espo\Modules\Sales\Tools\Subscription\Period\ValidationHelper;
use Espo\Modules\Sales\Tools\Sales\SaveOption;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\RemoveOptions;
use Espo\ORM\Repository\Option\SaveOptions;
use UnexpectedValueException;

/**
 * @implements BeforeSave<SubscriptionPeriod>
 * @implements BeforeRemove<SubscriptionPeriod>
 */
class Validate implements BeforeSave, BeforeRemove
{
    public static int $order = 12;

    public function __construct(
        private ValidationHelper $validationHelper,
    ) {}

    /**
     * @inheritDoc
     * @throws Conflict
     */
    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if (
            $options->get(SaveOption::VALIDATE_ALL) ||
            $options->get(SaveOption::VALIDATE_LOCKED)
        ) {
            $this->validateLocked($entity);
        }

        if ($options->get(SaveOption::VALIDATE_ALL)) {
            $this->validationHelper->validate($entity);
        } else {
            $this->validationHelper->validateRange($entity);
        }
    }

    /**
     * @inheritDoc
     * @throws Conflict
     */
    public function beforeRemove(Entity $entity, RemoveOptions $options): void
    {
        try {
            $subscription = $entity->getSubscription();
        } catch (UnexpectedValueException) {
            return;
        }

        if ($subscription->isLocked()) {
            throw new Conflict("Cannot remove period from a locked subscription.");
        }
    }

    /**
     * @throws Conflict
     */
    private function validateLocked(SubscriptionPeriod $entity): void
    {
        try {
            $subscription = $entity->getSubscription();
        } catch (UnexpectedValueException) {
            return;
        }

        if (!$subscription->isLocked()) {
            return;
        }

        $attributeList = [
            SubscriptionPeriod::FIELD_START_DATE,
            SubscriptionPeriod::FIELD_END_DATE,
            SubscriptionPeriod::FIELD_TYPE,
            SubscriptionPeriod::FIELD_STATUS,
            SubscriptionPeriod::FIELD_BILLING_STATUS,
        ];

        foreach ($attributeList as $attribute) {
            if ($entity->isAttributeChanged($attribute)) {
                throw Conflict::createWithBody(
                    "Can't modify the locked record.",
                    Body::create()
                        ->withMessageTranslation('cantModifyLocked', 'Quote', ['field' => $attribute])
                        ->encode()
                );
            }
        }
    }
}
