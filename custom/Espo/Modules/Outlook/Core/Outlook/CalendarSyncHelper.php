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

namespace Espo\Modules\Outlook\Core\Outlook;

use Espo\Core\ORM\Entity;
use Espo\Core\Utils\DateTime as DateTimeUtil;
use Espo\Entities\ExternalAccount;
use Espo\Modules\Outlook\Entities\OutlookCalendarEvent;
use Espo\Modules\Outlook\Entities\OutlookCalendarUser;
use Espo\ORM\EntityManager;

class CalendarSyncHelper
{
    public function __construct(
        private EntityManager $entityManager,
    ) {}

    /**
     * @param array<string, mixed> $response
     */
    public function processAfterPush(
        Entity $entity,
        OutlookCalendarUser $calendarUser,
        ExternalAccount $externalAccount,
        array $response,
    ): void {

        $eventId = $response['Id'] ?? $response['id'] ?? null;

        if (!$eventId) {
            return;
        }

        $iCalUId = $response['iCalUId'] ?? $response['uid'] ?? null;

        $isPrimary = !$this->entityManager
            ->getRDBRepositoryByClass(OutlookCalendarEvent::class)
            ->where([
                'entityId' => $entity->getId(),
                'entityType' => $entity->getEntityType(),
            ])
            ->findOne();

        $this->entityManager->createEntity(OutlookCalendarEvent::ENTITY_TYPE, [
            'entityId' => $entity->getId(),
            'entityType' => $entity->getEntityType(),
            'eventId' => $eventId,
            OutlookCalendarEvent::FIELD_I_CAL_UID => $iCalUId,
            'calendarId' => $calendarUser->getCalendarId(),
            'syncedAt' => date(DateTimeUtil::SYSTEM_DATE_TIME_FORMAT),
            'isEspoEvent' => true,
            'isPrimary' => $isPrimary,
            'userId' => $calendarUser->getUserId(),
            'outlookUserId' => $externalAccount->get('outlookUserId'),
        ]);
    }
}
