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

namespace Espo\Modules\Outlook\Repositories;

use Espo\Core\Repositories\Database;
use Espo\Modules\Outlook\Entities\OutlookCalendarEvent as OutlookCalendarEventEvent;
use Espo\ORM\Entity;
use RuntimeException;

/**
 * @extends Database<OutlookCalendarEventEvent>
 */
class OutlookCalendarEvent extends Database
{
    public function isEspoEventForAssignee(Entity $entity): bool
    {
        if (!$entity->get('assignedUserId')) {
            return false;
        }

        return (bool) $this
            ->where([
                'entityId' => $entity->getId(),
                'entityType' => $entity->getEntityType(),
                'isEspoEvent' => true,
                'userId' => $entity->get('assignedUserId'),
            ])
            ->findOne();
    }

    public function getEntityByCalendarIdEventId(string $calendarId, string $eventId): ?OutlookCalendarEventEvent
    {
        $list = $this
            ->where([
                'calendarId' => $calendarId,
                'eventId' => $eventId,
            ])
            ->limit(0, 5)
            ->find();

        foreach ($list as $item) {
            if ($item->getEventId() === $eventId) {
                return $item;
            }
        }

        return null;
    }

    public function getEventEntityByCalendarIdEventId(string $calendarId, string $eventId): ?Entity
    {
        $entity = $this->getEntityByCalendarIdEventId($calendarId, $eventId);

        if (!$entity) {
            return null;
        }

        if (!$entity->getTargetEntityType()) {
            throw new RuntimeException('OutlookCalendarEvent: Bad entity type.');
        }

        if (!$this->entityManager->hasRepository($entity->getTargetEntityType())) {
            throw new RuntimeException('OutlookCalendarEvent: Bad entity type.');
        }

        if (!$entity->getTargetEntityId()) {
            return null;
        }

        return $this->entityManager->getEntityById($entity->getTargetEntityType(), $entity->getTargetEntityId());
    }
}
