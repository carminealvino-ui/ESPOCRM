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

namespace Espo\Modules\Sales\Entities;

use Espo\Core\Field\Link;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\Modules\Sales\Tools\Sales\OrderItem;
use Espo\Modules\Sales\Tools\Subscription\IntervalUnit;
use Espo\Modules\Sales\Tools\SubscriptionTemplate\SubscriptionTemplateOrderItem;

/**
 * @extends OrderEntity<SubscriptionTemplateOrderItem>
 */
class SubscriptionTemplate extends OrderEntity
{
    public const ENTITY_TYPE = 'SubscriptionTemplate';

    public const STATUS_ACTIVE = 'Active';
    public const STATUS_INACTIVE = 'Inactive';

    public const FIELD_PRIMARY_PRODUCT = 'primaryProduct';
    public const FIELD_HAS_QUANTITY = 'hasQuantity';

    public function isLocked(): bool
    {
        return false;
    }

    public function getAccount(): ?Link
    {
        return null;
    }

    public function setPrimaryProduct(Link|Product|null $product): self
    {
        return $this->setRelatedLinkOrEntity(self::FIELD_PRIMARY_PRODUCT, $product);
    }

    public function hasTrial(): bool
    {
        return $this->get('hasTrial');
    }

    public function getTrialPeriodDays(): ?int
    {
        return $this->get('trialPeriodDays');
    }

    public function setTrialPeriodDays(?int $days ): self
    {
        return $this->set('trialPeriodDays', $days);
    }

    public function hasTerm(): bool
    {
        return $this->get('hasTerm');
    }

    public function setTermUnit(?IntervalUnit $unit): self
    {
        return $this->set('termUnit', $unit->value ?? null);
    }

    public function setTermLength(?int $length ): self
    {
        return $this->set('termLength', $length);
    }

    public function getTermUnit(): ?IntervalUnit
    {
        $raw = $this->get('termUnit');

        if (!$raw) {
            return null;
        }

        return IntervalUnit::tryFrom($raw);
    }

    public function getTermLength(): ?int
    {
        return $this->get('termLength');
    }

    /**
     * @return SubscriptionTemplateOrderItem[]
     */
    public function getItems(): array
    {
        return array_map(function (OrderItem $item) {
            return SubscriptionTemplateOrderItem::fromRaw($item->toRaw());
        }, parent::getItems());
    }
}
