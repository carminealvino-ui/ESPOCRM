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
 * License ID: 77350457a8d35522431c4daeee1dd4ad
 ************************************************************************************/

namespace Espo\Modules\Outlook\Entities;

use Espo\Core\Field\DateTime;
use Espo\Core\ORM\Entity;

class OutlookCalendarEvent extends Entity
{
    public const ENTITY_TYPE = 'OutlookCalendarEvent';

    public const FILED_IS_DELETED = 'isDeleted';
    public const FILED_IS_UPDATED = 'isUpdated';
    public const FIELD_I_CAL_UID = 'iCalUId';

    public function isEspoEvent(): bool
    {
        return (bool) $this->get('isEspoEvent');
    }

    public function isPrimary(): bool
    {
        return (bool) $this->get('isPrimary');
    }

    public function getTargetEntityType(): ?string
    {
        return $this->get('entityType');
    }

    public function getTargetEntityId(): ?string
    {
        return $this->get('entityId');
    }

    public function getEventId(): ?string
    {
        return $this->get('eventId');
    }

    public function getCalendarId(): ?string
    {
        return $this->get('calendarId');
    }

    public function isUpdated(): bool
    {
        return (bool) $this->get(self::FILED_IS_UPDATED);
    }

    public function isDeleted(): bool
    {
        return (bool) $this->get(self::FILED_IS_DELETED);
    }

    public function setIsUpdated(bool $isUpdated): self
    {
        $this->set(self::FILED_IS_UPDATED, $isUpdated);

        return $this;
    }

    public function setIsDeleted(bool $isDeleted): self
    {
        $this->set(self::FILED_IS_DELETED, $isDeleted);

        return $this;
    }

    public function setSyncedAt(?DateTime $syncedAt): self
    {
        return $this->setValueObject('syncedAt', $syncedAt);
    }
}
