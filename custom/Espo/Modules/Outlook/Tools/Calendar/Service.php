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

namespace Espo\Modules\Outlook\Tools\Calendar;

use Espo\Core\Exceptions\Error;
use Espo\Core\ExternalAccount\ClientManager;
use Espo\Core\Utils\Json;
use Espo\Entities\ExternalAccount;
use Espo\Modules\Crm\Entities\Meeting;
use Espo\Modules\Outlook\Core\Outlook\CalendarSyncHelper;
use Espo\Modules\Outlook\Core\Outlook\Clients\Calendar;
use Espo\Modules\Outlook\Core\Outlook\Clients\Outlook;
use Espo\Modules\Outlook\Core\Outlook\Exceptions\ApiError;
use Espo\Modules\Outlook\Core\Outlook\ItemPreparator;
use Espo\Modules\Outlook\Entities\OutlookCalendarUser;
use Espo\Modules\Outlook\Hooks\Meeting\PushExternalService;
use Espo\Modules\Outlook\Tools\Calendar\Exceptions\NotEnabled;
use Espo\ORM\EntityManager;
use LogicException;
use RuntimeException;

class Service
{
    public function __construct(
        private ClientManager $clientManager,
        private EntityManager $entityManager,
        private ItemPreparator $itemPreparator,
        private CalendarSyncHelper $syncHelper,
    ) {}

    /**
     * @throws Error
     * @throws NotEnabled
     * @throws ApiError
     */
    public function push(Meeting $meeting, PushParams $params): void
    {
        if (!$meeting->getAssignedUser()) {
            throw new RuntimeException("No assigned user.");
        }

        $client = $this->getClient($meeting);
        $externalAccount = $this->getExternalAccount($meeting);
        $calendarUser = $this->getCalendarUser($meeting);

        $item = $this->prepareItem($meeting, $params);

        $url = $client->buildUrl("calendars/{$calendarUser->getOutlookCalendarId()}/events");

        $response = $client->request($url, Json::encode($item), 'POST', 'application/json');

        $this->afterPush($meeting, $params, $response);

        $this->syncHelper->processAfterPush(
            entity: $meeting,
            calendarUser: $calendarUser,
            externalAccount: $externalAccount,
            response: $response,
        );
    }

    /**
     * @throws Error
     * @throws NotEnabled
     */
    private function getClient(Meeting $meeting): Calendar
    {
        $userId = $meeting->getAssignedUser()->getId() ?? throw new LogicException();

        $client = $this->clientManager->create('Outlook', $meeting->getAssignedUser()->getId());

        if (!$client) {
            $message = "Could not create online meeting. Outlook external account is not enabled for $userId.";

            throw new NotEnabled($message);
        }

        if (!$client instanceof Outlook) {
            throw new RuntimeException();
        }

        return $client->getCalendarClient();
    }

    /**
     * @throws NotEnabled
     */
    private function getCalendarUser(Meeting $meeting): OutlookCalendarUser
    {
        $userId = $meeting->getAssignedUser()->getId();

        if (!$userId) {
            throw new LogicException();
        }

        $calendarUser = $this->entityManager
            ->getRDBRepositoryByClass(OutlookCalendarUser::class)
            ->where([
                'active' => true,
                'userId' => $meeting->getAssignedUser()->getId(),
            ])
            ->findOne();

        if (!$calendarUser) {
            throw new NotEnabled("Calendar not enabled for $userId.");
        }

        return $calendarUser;
    }

    /**
     * @throws NotEnabled
     */
    private function getExternalAccount(Meeting $meeting): ExternalAccount
    {
        $userId = $meeting->getAssignedUser()->getId();

        if (!$userId) {
            throw new LogicException();
        }

        $externalAccount = $this->entityManager->getEntityById(ExternalAccount::ENTITY_TYPE, 'Outlook__' . $userId);

        if (!$externalAccount) {
            throw new NotEnabled("External account not enabled for $userId.");
        }

        if (!$externalAccount instanceof ExternalAccount) {
            throw new LogicException();
        }

        return $externalAccount;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function afterPush(Meeting $meeting, PushParams $params, array $response): void
    {
        if (
            $params->onlineMeeting &&
            method_exists($meeting, 'setJoinUrl') &&
            isset($response['onlineMeeting']['joinUrl'])
        ) {
            $meeting->setJoinUrl($response['onlineMeeting']['joinUrl']);

            if (!$meeting->get('externalService')) {
                $meeting->set('externalService', 'Microsoft');
            }

            $this->entityManager->saveEntity($meeting, [
                PushExternalService::OPTION_SKIP_EXTERNAL_SERVICE => true,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareItem(Meeting $meeting, PushParams $params): array
    {
        $item = $this->itemPreparator->prepare($meeting, true, ['syncAttendees' => true]);

        if ($params->onlineMeeting) {
            $item['isOnlineMeeting'] = true;
            $item['onlineMeetingProvider'] = 'teamsForBusiness';
        }

        return $item;
    }
}
