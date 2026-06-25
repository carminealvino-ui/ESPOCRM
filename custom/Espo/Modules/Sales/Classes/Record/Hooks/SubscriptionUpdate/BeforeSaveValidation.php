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

namespace Espo\Modules\Sales\Classes\Record\Hooks\SubscriptionUpdate;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Record\Hook\SaveHook;
use Espo\Modules\Sales\Entities\SubscriptionUpdate;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\Modules\Sales\Tools\Sales\RecordValidator;
use Espo\Modules\Sales\Tools\Subscription\UpdateAmounts\Data;
use Espo\Modules\Sales\Tools\Subscription\UpdateService;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * @implements SaveHook<SubscriptionUpdate>
 */
class BeforeSaveValidation implements SaveHook
{
    public function __construct(
        private RecordValidator $recordValidator,
        private UpdateService $updateService,
    ) {}

    public function process(Entity $entity): void
    {
        $this->recordValidator->process($entity);

        $this->validateSame($entity);
        $this->validateUpdate($entity);
        $this->validateStatus($entity);
    }

    /**
     * @throws Forbidden
     */
    private function validateUpdate(SubscriptionUpdate $entity): void
    {
        if (
            $entity->isNew() ||
            $entity->getStatus() !== SubscriptionUpdate::STATUS_DRAFT
        ) {
            return;
        }

        if (
            $entity->isAttributeChanged(OrderEntity::ATTR_ITEM_LIST) ||
            $entity->isAttributeChanged(SubscriptionUpdate::FIELD_STATUS) ||
            $entity->isAttributeChanged(SubscriptionUpdate::FIELD_DATE)
        ) {
            throw Forbidden::createWithBody(
                'cannotUpdateNonDraft',
                Body::create()
                    ->withMessageTranslation('cannotUpdateNonDraft', SubscriptionUpdate::ENTITY_TYPE)
            );
        }
    }

    /**
     * @throws BadRequest
     */
    private function validateSame(SubscriptionUpdate $entity): void
    {
        if (
            !$entity->isNew() ||
            $entity->getStatus() == SubscriptionUpdate::STATUS_DRAFT
        ) {
            return;
        }

        $data = Data::fromEntity($entity);

        if ($this->updateService->isSame($entity->getSubscription(), $data)) {
            throw BadRequest::createWithBody(
                'noChangesInUpdate',
                Body::create()
                    ->withMessageTranslation('noChangesInUpdate', SubscriptionUpdate::ENTITY_TYPE)
            );
        }
    }

    /**
     * @throws BadRequest
     */
    private function validateStatus(SubscriptionUpdate $entity): void
    {
        if (
            $entity->isAttributeChanged(OrderEntity::FIELD_STATUS) &&
            $entity->getStatus() !== SubscriptionUpdate::STATUS_APPLIED
        ) {
            throw new BadRequest("Status must be 'Applied'.");
        }
    }
}
