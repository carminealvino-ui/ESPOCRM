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

use Espo\Core\ORM\Entity;

class PaymentTermsProfileItem extends Entity
{
    public const ENTITY_TYPE = 'PaymentTermsProfileItem';

    public const FIELD_PERCENTAGE = 'percentage';
    public const FIELD_DAYS = 'days';
    public const FIELD_ORDER = 'order';

    public const ATTR_PROFILE_ID = 'profileId';

    public function setOrder(int $order): self
    {
        return $this->set(self::FIELD_ORDER, $order);
    }

    /**
     * @return numeric-string
     */
    public function getPercentage(): string
    {
        $value = $this->get(self::FIELD_PERCENTAGE);

        if ($value === null) {
            $value = '0.00';
        }

        return $value;
    }

    public function getDays(): int
    {
        return $this->get(self::FIELD_DAYS) ?? 0;
    }

    /**
     * @param numeric-string $percentage
     */
    public function setPercentage(string $percentage): self
    {
        return $this->set(self::FIELD_PERCENTAGE, $percentage);
    }

    public function setDays(int $days): self
    {
        return $this->set(self::FIELD_DAYS, $days);
    }

    public function setProfileId(string $profileId): self
    {
        return $this->set(self::ATTR_PROFILE_ID, $profileId);
    }
}
