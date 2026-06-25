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

namespace Espo\Modules\Outlook\Hooks\ExternalAccount;

use Espo\Modules\Outlook\Core\Outlook\CalendarManager;
use Espo\Modules\Outlook\Core\Outlook\Clients\Outlook as OutlookClient;
use Espo\Modules\Outlook\Repositories\OutlookCalendar;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

use Espo\Core\Exceptions\Error;
use Espo\Core\ExternalAccount\ClientManager;
use RuntimeException;

class Outlook
{
    public static $order = 9;

    private EntityManager $entityManager;
    private ClientManager $externalAccountClientManager;

    public function __construct(
        EntityManager $entityManager,
        ClientManager $externalAccountClientManager
    ) {
        $this->entityManager = $entityManager;
        $this->externalAccountClientManager = $externalAccountClientManager;
    }

    public function afterSave(Entity $entity, array $options): void
    {
        [$integration, $userId] = explode('__', $entity->get('id'));

        if ($integration !== 'Outlook') {
            return;
        }

        if (!empty($options['isTokenRenewal'])) {
            return;
        }

        $direction = $entity->get('calendarDirection');

        $monitoredCalendarIds = $entity->get('calendarMonitoredCalendarsIds') ?? [];
        $monitoredCalendarNames = $entity->get('calendarMonitoredCalendarsNames') ?? (object) [];

        $mainCalendarId = $entity->get('calendarMainCalendarId');
        $mainCalendarName = $entity->get('calendarMainCalendarName');

        $monitoredHash = [];

        $monitoredList = $this->entityManager
            ->getRDBRepository('OutlookCalendarUser')
            ->where([
                'type' => 'monitored',
                'userId' => $userId,
            ])
            ->find();

        foreach ($monitoredList as $item) {
            $monitoredHash[$item->get('calendarId')] = $item;
        }

        $mainHash = [];

        $mainList = $this->entityManager
            ->getRDBRepository('OutlookCalendarUser')
            ->where([
                'type' => 'main',
                'userId' => $userId,
            ])
            ->find();

        foreach ($mainList as $item) {
            $mainHash[$item->get('calendarId')] = $item;
        }

        if ($direction == CalendarManager::DIRECTION_OUTLOOK_TO_ESPO) {
            if (!in_array($mainCalendarId, $monitoredCalendarIds)) {
                $monitoredCalendarIds[] = $mainCalendarId;
                $monitoredCalendarNames->$mainCalendarId = $mainCalendarName;
            }

            $mainCalendarId = null;
            $mainCalendarName = null;
        }

        if ($direction === CalendarManager::DIRECTION_ESPO_TO_OUTLOOK) {
            $monitoredCalendarIds = [];
        }

        /** @var OutlookCalendar $repo */
        $repo = $this->entityManager->getRepository('OutlookCalendar');

        foreach ($monitoredCalendarIds as $calendarId) {
            if ($calendarId === $mainCalendarId) {
                continue;
            }

            $outlookCalendar = $repo->getByOutlookCalendarId($calendarId);

            if (!$outlookCalendar) {
                $outlookCalendar = $this->entityManager->getNewEntity('OutlookCalendar');

                $outlookCalendar->set('name', $monitoredCalendarNames->$calendarId);
                $outlookCalendar->set('calendarId', $calendarId);

                $this->entityManager->saveEntity($outlookCalendar);
            }

            $id = $outlookCalendar->get('id');

            if (isset($monitoredHash[$id])) {
                if (!$monitoredHash[$id]->get('active')) {
                    $monitoredHash[$id]->set('active', true);

                    $this->entityManager->saveEntity($monitoredHash[$id]);
                }
            }
            else {
                $calendarUser = $this->entityManager->getNewEntity('OutlookCalendarUser');

                $calendarUser->set('userId', $userId);
                $calendarUser->set('type', 'monitored');
                $calendarUser->set('calendarId', $id);

                $this->entityManager->saveEntity($calendarUser);
            }
        }

        foreach ($monitoredHash as $item) {
            if (
                $item->get('active') &&
                (
                    !is_array($monitoredCalendarIds) ||
                    !in_array($item->get('outlookCalendarId'), $monitoredCalendarIds)
                )
            ) {
                $item->set('active', false);

                $this->entityManager->saveEntity($item);
            }
        }

        if (!$mainCalendarId) {
            foreach ($mainHash as $item) {
                if ($item->get('active')) {
                    $item->set('active', false);
                    $this->entityManager->saveEntity($item);
                }
            }
        }
        else {
            $outlookCalendar = $repo->getByOutlookCalendarId($mainCalendarId);

            if (!$outlookCalendar) {
                $outlookCalendar = $this->entityManager->getNewEntity('OutlookCalendar');

                $outlookCalendar->set('name', $mainCalendarName);
                $outlookCalendar->set('calendarId', $mainCalendarId);

                $this->entityManager->saveEntity($outlookCalendar);
            }

            $id = $outlookCalendar->get('id');

            foreach ($mainHash as $calendarId => $item) {
                if ($item->get('active') && $id !== $calendarId) {
                    $item->set('active', false);

                    $this->entityManager->saveEntity($item);
                }
                else if (!$item->get('active') && $id === $calendarId) {
                    $item->set('active', true);

                    $this->entityManager->saveEntity($item);
                }
            }

            if (!isset($mainHash[$id])) {
                $item = $this->entityManager->getNewEntity('OutlookCalendarUser');

                $item->set('userId', $userId);
                $item->set('type', 'main');
                $item->set('calendarId', $id);

                $this->entityManager->saveEntity($item);
            }
        }
    }

    public function beforeSave(Entity $entity): void
    {
        [$integration, $userId] = explode('__', $entity->get('id'));

        if ($integration !== 'Outlook') {
            return;
        }

        $prevEntity = $this->entityManager->getEntityById('ExternalAccount', $entity->get('id'));

        if ($prevEntity && $prevEntity->get('calendarStartDate') > $entity->get('calendarStartDate')) {
            $calendarUserList = $this->entityManager
                ->getRDBRepository('OutlookCalendarUser')
                ->where([
                    'active' => true,
                    'userId' => $userId,
                ])
                ->find();

            foreach ($calendarUserList as $calendarUser) {
                $calendarUser->set('skipToken', null);
                $calendarUser->set('deltaToken', null);
                $calendarUser->set('lastSyncedAt', null);

                $this->entityManager->saveEntity($calendarUser);
            }
        }
    }

    /**
     * @throws Error
     * @noinspection PhpUnused
     */
    public function afterConnect(Entity $entity, array $options): void
    {
        if ($options['integration'] !== 'Outlook') {
            return;
        }

        $clientManager = $this->externalAccountClientManager;

        /** @var ?OutlookClient $client */
        $client = $clientManager->create($options['integration'], $options['userId']);

        if (!$client) {
            throw new RuntimeException();
        }

        $isMail = false;

        $userId = $options['userId'];

        if (!$this->entityManager->getEntityById('User', $userId)) {
            $isMail = true;
        }

        if ($isMail) {
            return;
            //$client = $client->getMailClient();
        }

        $result = $client->requestUserData();

        if (empty($result)) {
            throw new RuntimeException("Outlook did not return user data.");
        }

        $outlookUserId = $result['Id'] ?? $result['id'] ?? null;

        if (!$outlookUserId) {
            throw new RuntimeException("Outlook did not return user ID.");
        }

        $entity->set('outlookUserId', $outlookUserId);

        $this->entityManager->saveEntity($entity);
    }
}
