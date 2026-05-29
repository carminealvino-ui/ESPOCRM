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

namespace Espo\Modules\Outlook\Hooks\Common;

use Espo\Modules\Crm\Entities\Call;
use Espo\Modules\Crm\Entities\Meeting;
use Espo\Modules\Outlook\Entities\OutlookCalendarEvent;
use Espo\ORM\Entity;

use Espo\Core\Templates\Entities\Event;
use Espo\ORM\EntityManager;

/**
 * @noinspection PhpUnused
 */
class OutlookCalendar
{
    public static $order = 8;

    public function __construct(
        private EntityManager $entityManager,
    ) {}

    public function afterSave(Entity $entity, array $options): void
    {
        if (
            !empty($options['silent']) ||
            !empty($options['skipOutlookSync'])
        ) {
            return;
        }

        if (
            !$entity instanceof Meeting &&
            !$entity instanceof Call &&
            !$entity instanceof Event
        ) {
            return;
        }

        if ($entity->isNew()) {
            return;
        }

        $calendarId = null;

        if (!empty($options['isOutlookSync'])) {
            $calendarId = $options['calendarId'];
        }

        $isChanged = false;

        $attributeList = [
            'name',
            'dateStart',
            'dateEnd',
            'isAllDay',
            'description',
        ];

        if ($entity instanceof Meeting) {
            $attributeList[] = 'joinUrl';
        }

        if ($entity instanceof Meeting || $entity instanceof Call) {
            $attributeList[] = 'usersIds';
            $attributeList[] = 'contactsIds';
            $attributeList[] = 'leadsIds';
        }

        if ($entity->hasAttribute('cLocation')) {
            $attributeList[] = 'cLocation';
        }

        foreach ($attributeList as $attribute) {
            if ($entity->isAttributeChanged($attribute)) {
                $isChanged = true;
            }
        }

        $isStatusChanged = false;

        // Outlook does not allow to set isCancelled. Instead, a cancel action should be called.
        if (
            $entity->isAttributeChanged('status') &&
            (
                $entity->getStatus() === Meeting::STATUS_NOT_HELD ||
                $entity->getFetched('status') === Meeting::STATUS_NOT_HELD
            )
        ) {
            $isStatusChanged = true;
        }

        $isAssigneeChanged = $entity->isAttributeChanged('assignedUserId');

        if (!$isChanged && !$isStatusChanged && !$isAssigneeChanged) {
            return;
        }

        $events = $this->entityManager
            ->getRDBRepositoryByClass(OutlookCalendarEvent::class)
            ->where([
                'entityId' => $entity->getId(),
                'entityType' => $entity->getEntityType(),
                'outlookDeleted' => false,
            ])
            ->find();

        foreach ($events as $event) {
            if ($event->getCalendarId() === $calendarId) {
                continue;
            }

            if ($event->isEspoEvent()) {
                if ($entity->getStatus() === Meeting::STATUS_NOT_HELD) {
                    $event->setIsDeleted(true);
                } else if ($event->isDeleted()) {
                    $event->setIsDeleted(false);
                }

                if ($isAssigneeChanged) {
                    // To delete the event. The new will be created in the calendar of the new organizer (assignee).
                    $event->setIsDeleted(true);
                }
            }

            if ($isChanged && !$event->isDeleted()) {
                $event->setIsUpdated(true);
            }

            $this->entityManager->saveEntity($event);
        }
    }

    public function afterRemove(Entity $entity, $options): void
    {
        if (!empty($options['silent'])) {
            return;
        }

        if (
            !$entity instanceof Meeting &&
            !$entity instanceof Call &&
            !$entity instanceof Event
        ) {
            return;
        }


        $calendarId = null;

        if (!empty($options['isOutlookSync'])) {
            $calendarId = $options['calendarId'];
        }

        $events = $this->entityManager
            ->getRDBRepositoryByClass(OutlookCalendarEvent::class)
            ->where([
                'entityId' => $entity->getId(),
                'entityType' => $entity->getEntityType(),
                'outlookDeleted' => false,
            ])
            ->find();

        foreach ($events as $event) {
            if ($event->getCalendarId() === $calendarId) {
                continue;
            }

            $event->setIsDeleted(true);

            $this->entityManager->saveEntity($event);
        }
    }
}
