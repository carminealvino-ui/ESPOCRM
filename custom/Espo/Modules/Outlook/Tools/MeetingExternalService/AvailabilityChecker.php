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

namespace Espo\Modules\Outlook\Tools\MeetingExternalService;

use Espo\Core\AclManager;
use Espo\Entities\Integration;
use Espo\Entities\User;
use Espo\Modules\Crm\Tools\Meeting\MeetingServiceAvailabilityChecker;
use Espo\ORM\EntityManager;

/**
 * @noinspection PhpUnused
 */
class AvailabilityChecker implements MeetingServiceAvailabilityChecker
{
    public function __construct(
        private AclManager $aclManager,
        private EntityManager $entityManager,
    ) {}

    public function check(User $user): bool
    {
        if (!$this->aclManager->checkScope($user, 'OutlookCalendar')) {
            return false;
        }

        $integration = $this->entityManager
            ->getRDBRepositoryByClass(Integration::class)
            ->getById('Outlook');

        if (!$integration || !$integration->isEnabled() || !$integration->get('microsoftTeamsMeetings')) {
            return false;
        }

        return true;
    }
}
