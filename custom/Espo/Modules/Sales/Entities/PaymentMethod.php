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
use RuntimeException;

class PaymentMethod extends Entity
{
    public const ENTITY_TYPE = 'PaymentMethod';

    public const STATUS_ACTIVE = 'Active';

    public const FIELD_IS_INBOUND = 'isInbound';
    public const FIELD_IS_OUTBOUND = 'isOutbound';
    public const FIELD_STATUS = 'status';
    public const FIELD_CHANNEL = 'channel';
    public const FIELD_INSTRUCTIONS = 'instructions';

    public function getStatus(): string
    {
        return $this->get(self::FIELD_STATUS);
    }

    public function getChannel(): ?PaymentChannel
    {
        $channel = $this->relations->getOne(self::FIELD_CHANNEL);

        if (!$channel) {
            return null;
        }

        if (!$channel instanceof PaymentChannel) {
            throw new RuntimeException();
        }

        return $channel;
    }

    public function setChannel(?PaymentChannel $channel): self
    {
        return $this->setRelatedLinkOrEntity(self::FIELD_CHANNEL, $channel);
    }

    public function getInstructions(): ?string
    {
        return $this->get(self::FIELD_INSTRUCTIONS);
    }

    public function getName(): ?string
    {
        return $this->get('name');
    }

    public function isInbound(): bool
    {
        return $this->get(self::FIELD_IS_INBOUND);
    }

    public function isOutbound(): bool
    {
        return $this->get(self::FIELD_IS_OUTBOUND);
    }
}
