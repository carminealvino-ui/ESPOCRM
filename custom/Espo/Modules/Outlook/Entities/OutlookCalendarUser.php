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

use Espo\Core\ORM\Entity;

class OutlookCalendarUser extends Entity
{
    public const ENTITY_TYPE = 'OutlookCalendarUser';

    public function getOutlookCalendarId(): ?string
    {
        return $this->get('outlookCalendarId');
    }

    public function getCalendarId(): ?string
    {
        return $this->get('calendarId');
    }

    public function getUserId(): ?string
    {
        return $this->get('userId');
    }

    public function getType(): ?string
    {
        return $this->get('type');
    }

    public function getDeltaToken(): ?string
    {
        return $this->get('deltaToken');
    }

    public function getSkipToken(): ?string
    {
        return $this->get('skipToken');
    }

    public function setDeltaToken(?string $token): self
    {
        $this->set('deltaToken', $token);

        return $this;
    }

    public function setSkipToken(?string $token): self
    {
        $this->set('skipToken', $token);

        return $this;
    }
}
