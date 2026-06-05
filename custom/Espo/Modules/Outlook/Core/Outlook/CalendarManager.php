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

use Espo\Core\Acl\Table;
use Espo\Core\AclManager;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\ExternalAccount\ClientManager;
use Espo\Core\Field\DateTime as DateTimeValue;
use Espo\Core\Name\Field;
use Espo\Core\ORM\Entity;
use Espo\Core\Select\SelectBuilderFactory;
use Espo\Core\Templates\Entities\Event;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\DateTime as DateTimeUtil;
use Espo\Core\Utils\Log;
use Espo\Entities\Integration;
use Espo\Entities\User;
use Espo\Modules\Crm\Entities\Account;
use Espo\Modules\Crm\Entities\Call;
use Espo\Modules\Crm\Entities\Contact;
use Espo\Modules\Crm\Entities\Lead;
use Espo\Modules\Crm\Entities\Meeting;
use Espo\Modules\Outlook\Core\Outlook\Clients\Calendar;
use Espo\Modules\Outlook\Core\Outlook\Clients\Outlook;
use Espo\Modules\Outlook\Entities\OutlookCalendarEvent;
use Espo\Modules\Outlook\Entities\OutlookCalendarUser;
use Espo\Entities\ExternalAccount;
use Espo\Modules\Outlook\Core\Outlook\Exceptions\ApiError;

use DateTime;
use Espo\Modules\Outlook\Repositories\OutlookCalendarEvent as OutlookCalendarEventRepo;
use Espo\ORM\EntityManager;
use Espo\ORM\Query\SelectBuilder;
use Espo\Tools\EmailAddress\EntityLookup;
use Exception;
use RuntimeException;
use stdClass;

class CalendarManager
{
    const SYNC_MAX_PAGE_SIZE = 20;
    const PUSH_PORTION_SIZE = 20;
    const END_PERIOD = '3 months';

    private $userClientMap = [];

    /** @var array<string, User> */
    private $userMap = [];

    public const DIRECTION_OUTLOOK_TO_ESPO = 'OutlookToEspo';
    public const DIRECTION_ESPO_TO_OUTLOOK = 'EspoToOutlook';
    public const DIRECTION_BOTH = 'Both';

    private const CALENDAR_USER_TYPE_MAIN = 'main';

    private $userWithFetchIntegrationIdList;

    public function __construct(
        private EntityManager $entityManager,
        private ClientManager $clientManager,
        private Config $config,
        private AclManager $aclManager,
        private SelectBuilderFactory $selectBuilderFactory,
        private Log $log,
        private ItemPreparator $itemPreparator,
        private CalendarSyncHelper $syncHelper,
        private EntityLookup $entityLookup,
    ) {}

    /**
     * @throws Error
     */
    private function getUserClient(string $userId): Calendar
    {
        if (!array_key_exists($userId, $this->userClientMap)) {
            /** @var Outlook $client */
            $client = $this->clientManager->create('Outlook', $userId);

            $this->userClientMap[$userId] = $client->getCalendarClient();
        }

        return $this->userClientMap[$userId];
    }

    private function getUserById(string $userId): User
    {
        if (!isset($this->userMap[$userId])) {
            $this->userMap[$userId] = $this->entityManager->getEntityById(User::ENTITY_TYPE, $userId);
        }

        $user = $this->userMap[$userId];

        if (!$user) {
            throw new RuntimeException("Outlook sync: user $userId not found.");
        }

        return $user;
    }

    /**
     * @return string[]
     */
    private function getUserWithFetchIntegrationIdList(): array
    {
        if (isset($this->userWithFetchIntegrationIdList)) {
            return $this->userWithFetchIntegrationIdList;
        }

        $userList = $this->entityManager
            ->getRDBRepository(User::ENTITY_TYPE)
            ->select(['id'])
            ->where([
                'type' => ['admin', 'regular'],
                'isActive' => true,
            ])
            ->find();

        $userWithFetchIntegrationIdList = [];

        foreach ($userList as $user) {
            $ea = $this->entityManager
                ->getRepository('ExternalAccount')
                ->getById('Outlook__' . $user->getId());

            if (
                $ea->get('outlookCalendarEnabled') &&
                $ea->get('calendarDirection') !== self::DIRECTION_OUTLOOK_TO_ESPO
            ) {
                $userWithFetchIntegrationIdList[] = $user->getId();
            }
        }

        $this->userWithFetchIntegrationIdList = $userWithFetchIntegrationIdList;

        return $userWithFetchIntegrationIdList;
    }

    /**
     * @param array<string, mixed> $params
     * @throws Error
     */
    public function getCalendarList(string $userId, $params = [])
    {
        $result = (object) [];

        $response = $this->getUserClient($userId)->getCalendarList($params);

        if (is_array($response) && isset($response['value'])) {
            foreach ($response['value'] as $item) {
                $id = $item['id'] ?? $item['Id'];
                $name = $item['name'] ?? $item['Name'];

                $result->$id = $name;
            }
         }

         return $result;
    }

    /**
     * @throws Error
     * @throws ApiError
     */
    public function syncCalendarToMicrosoft(OutlookCalendarUser $calendarUser, ExternalAccount $externalAccount): void
    {
        $this->runSync($calendarUser, $externalAccount, true);
    }

    /**
     * @throws Error
     * @throws ApiError
     */
    public function syncCalendarToEspo(OutlookCalendarUser $calendarUser, ExternalAccount $externalAccount): void
    {
        $this->runSync($calendarUser, $externalAccount, false);
    }

    /**
     * @throws Error
     * @throws ApiError
     */
    private function runSync(OutlookCalendarUser $calendarUser, ExternalAccount $externalAccount, bool $out): void
    {
        $nowString = (new DateTime())->format(DateTimeUtil::SYSTEM_DATE_TIME_FORMAT);

        $direction = $externalAccount->get('calendarDirection');

        if (!$direction) {
            return;
        }

        if (!$out && ($direction === self::DIRECTION_OUTLOOK_TO_ESPO || $direction === self::DIRECTION_BOTH)) {
            $this->fetchFromOutlook($calendarUser, $externalAccount);
        }

        if ($out && ($direction === self::DIRECTION_ESPO_TO_OUTLOOK || $direction === self::DIRECTION_BOTH)) {
            $this->pushToOutlook($calendarUser, $externalAccount);
        }

        $calendarUser->set('lastSyncedAt', $nowString);

        $this->entityManager->saveEntity($calendarUser);
    }

    /**
     * @throws ApiError
     * @throws Error
     * @throws Exception
     */
    public function fetchFromOutlook(OutlookCalendarUser $calendarUser, ExternalAccount $externalAccount): void
    {
        $userId = $calendarUser->getUserId();

        $nowString = (new DateTime())->format(DateTimeUtil::SYSTEM_DATE_TIME_FORMAT);

        $endPeriod = $this->config->get('outlookCalendarSyncEndPeriod', self::END_PERIOD);

        try {
            $end = (new DateTime())->modify('+' . $endPeriod)->format(DateTimeUtil::SYSTEM_DATE_FORMAT);
        } catch (Exception $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }

        /** @var ?int $synMaxPageSize */
        $synMaxPageSize = $this->config->get('outlookCalendarSyncMaxPortionSize', self::SYNC_MAX_PAGE_SIZE);
        /** @var ?string $calendarStartDate */
        $calendarStartDate = $externalAccount->get('calendarStartDate');

        $params = [
            'start' => $calendarStartDate,
            'end' => $end,
            'maxPageSize' => $synMaxPageSize,
            'deltaToken' => $calendarUser->getDeltaToken(),
            'skipToken' => $calendarUser->getSkipToken(),
        ];

        try {
            $result = $this->getUserClient($userId)
                ->requestSync($calendarUser->getOutlookCalendarId(), $params);
        } catch (Exception $e) {
            $result = $this->fetchFromOutlookAfterException(
                exception: $e,
                calendarUser: $calendarUser,
                userId: $userId,
                nowString: $nowString,
                end: $end,
                synMaxPageSize: $synMaxPageSize,
            );
        }

        if (isset($result['skipToken']) || isset($result['deltaToken'])) {
            $calendarUser->setDeltaToken(null);
            $calendarUser->setSkipToken(null);

            if (isset($result['skipToken'])) {
                $calendarUser->setSkipToken($result['skipToken']);
            } else if (isset($result['deltaToken'])) {
                $calendarUser->setDeltaToken($result['deltaToken']);
            }
        }

        /** @var array<string, mixed>[] $itemList */
        $itemList = $result['itemList'] ?? [];

        foreach ($itemList as $item) {
            try {
                $this->processOutlookItemSync($calendarUser, $externalAccount, (object) $item);
            } catch (Exception $e) {
                $this->log->error($e->getMessage());
            }
        }

        $isSyncFinished = $result['isSyncFinished'] ?? false;

        if ($isSyncFinished) {
            $calendarUser->set('lastSyncedAt', $nowString);
        }

        $this->entityManager->saveEntity($calendarUser);
    }

    /**
     * @return array<string, string>
     */
    private function buildIdentityLabelMap(ExternalAccount $externalAccount)
    {
        $identLabelMap = [];
        $defaultEntityType = $externalAccount->get('calendarDefaultEntity');

        foreach ($externalAccount->get('calendarEntityTypes') ?? [] as $itemEntityType) {
            $identLabel = $externalAccount->get($itemEntityType . 'IdentificationLabel') ?? '';

            if ($itemEntityType !== $defaultEntityType && $identLabel) {
                $identLabelMap[$itemEntityType] = $identLabel;
            }
        }

        return $identLabelMap;
    }

    private function processOutlookItemSync(
        OutlookCalendarUser $calendarUser,
        ExternalAccount $externalAccount,
        stdClass $item,
    ): void {

        $reason = $item->Reason ?? $item->reason ?? null;
        $id = $item->Id ?? $item->id ?? null;
        $type = $item->Type ?? $item->type ?? null;

        if (!$reason && isset($item->{'@removed'})) {
            $reason = $item->{'@removed'}['reason'] ?? null;
        }

        if (is_string($type)) {
            $type = lcfirst($type);
        }

        if ($type === 'occurrence' || $type === 'seriesMaster') {
            return;
        }

        if (!$id) {
            throw new RuntimeException("Outlook sync: No event id.");
        }

        if (str_starts_with($id, 'CalendarView(') || str_starts_with($id, 'calendarView(')) {
            $id = substr($id, 14);
            $id = substr($id, 0, -2);

            if (empty($id)) {
                throw new RuntimeException("Outlook sync: Bad event id.");
            }
        }

        $params = [
            'defaultEntity' => $externalAccount->get('calendarDefaultEntity'),
            'labelMap' => $this->buildIdentityLabelMap($externalAccount),
            'createContacts' => $externalAccount->get('calendarCreateContacts'),
            'skipPrivate' => $externalAccount->get('calendarSkipPrivate'),
        ];

        $outlookUserId = $externalAccount->get('outlookUserId');

        if ($reason === 'deleted') {
            $this->processDeleteEvent($calendarUser, $id);

            return;
        }

        $this->processCreateUpdateEvent(
            calendarUser: $calendarUser,
            outlookUserId: $outlookUserId,
            params: $params,
            eventId: $id,
            item: $item,
        );
    }

    private function getEventRepo(): OutlookCalendarEventRepo
    {
        /** @var OutlookCalendarEventRepo */
        return $this->entityManager->getRepository('OutlookCalendarEvent');
    }

    private function processDeleteEvent(
        OutlookCalendarUser $calendarUser,
        string $eventId
    ): void {

        $entity = $this->getEventRepo()->getEventEntityByCalendarIdEventId(
            $calendarUser->getCalendarId(),
            $eventId
        );

        //$userId = $calendarUser->get('userId');
        //$user = $this->getUserById($userId);

        $relationEvent = $this->getEventRepo()
            ->getEntityByCalendarIdEventId(
                $calendarUser->getCalendarId(),
                $eventId
            );

        // Supposed to be an organizer.
        // Organizer is available for deletions.
        $isAssignee = $entity && $entity->get('assignedUserId') === $calendarUser->getUserId();

        //$isEspoEvent = false;

        if ($relationEvent) {
            $isEspoEvent = $relationEvent->get('isEspoEvent');

            if ($isEspoEvent || !$isAssignee) {
                $relationEvent->set('outlookDeleted', true);

                $this->entityManager->saveEntity($relationEvent);

                return;
            }

            $this->entityManager->removeEntity($relationEvent);
        }

        if (!$entity || !$isAssignee) {
            return;
        }

        /*
        if ($isEspoEvent) {
            return;
        }

        if ($isEspoEvent && !$this->aclManager->check($user, $entity, 'delete')) {
            $this->log->info("Outlook sync: No access to delete event for user {$userId}.");

            return;
        }*/

        $this->entityManager->removeEntity($entity, [
            'isOutlookSync' => true,
            'noNotifications' => true,
            'calendarId' => $calendarUser->getCalendarId(),
        ]);
    }

    /**
     * @param array{
     *     defaultEntity: string,
     *     labelMap: array<string, string>,
     *     skipPrivate: bool,
     *     createContacts: bool,
     * } $params
     */
    private function processCreateUpdateEventNoLink(
        OutlookCalendarUser $calendarUser,
        string $outlookUserId,
        array $params,
        string $eventId,
        stdClass $item,
    ): void {

        $userId = $calendarUser->getUserId();
        $user = $this->getUserById($userId);

        $iCalUId = $item->iCalUId ?? $item->uid ?? null;

        $skipSave = false;
        $entity = null;

        $itemData = $this->getDataFromItem($item);

        if ($itemData->isPrivate && $params['skipPrivate']) {
            return;
        }

        $altLink = $iCalUId ? $this->getOutlookCalendarEventByICalUid($iCalUId) : null;

        if ($altLink) {
            if ($altLink->isEspoEvent()) {
                return;
            }

            $entity = $this->getEntityForOutlookCalendarEvent($altLink);

            if (!$entity) {
                return;
            }
        }

        $isPrimary = false;

        if (!$entity) {
            if ($itemData->isCancelled) {
                return;
            }

            $entityType = $params['defaultEntity'];

            if (!$this->aclManager->check($user, $entityType, Table::ACTION_CREATE)) {
                $this->log->info("Outlook sync: No access to create event $entityType for user $userId.");

                return;
            }

            $isPrimary = true;

            $entity = $this->entityManager->getNewEntity($entityType);

            if (
                !$entity instanceof Meeting &&
                !$entity instanceof Call &&
                !$entity instanceof Event
            ) {
                return;
            }

            $this->populateEntityWithItemData(
                entity: $entity,
                itemData: $itemData,
                userId: $userId,
                iCalUId: $iCalUId,
                item: $item,
                user: $user,
                params: $params,
            );
        } else {
            $this->updateEntityOrganizer(
                entity: $entity,
                userId: $userId,
                skipSave: $skipSave,
                itemData: $itemData,
            );
        }

        if ($itemData->isCancelled) {
            $entity->setStatus(Meeting::STATUS_NOT_HELD);
        }

        if (!$skipSave) {
            $this->entityManager->saveEntity($entity, [
                'isOutlookSync' => true,
                'noNotifications' => true,
                'calendarId' => $calendarUser->getCalendarId(),
            ]);
        }

        $this->entityManager->createEntity(OutlookCalendarEvent::ENTITY_TYPE, [
            'entityType' => $entity->getEntityType(),
            'entityId' => $entity->getId(),
            'calendarId' => $calendarUser->getCalendarId(),
            'eventId' => $eventId,
            'syncedAt' => date(DateTimeUtil::SYSTEM_DATE_TIME_FORMAT),
            OutlookCalendarEvent::FIELD_I_CAL_UID => $iCalUId,
            'isPrimary' => $isPrimary,
            'userId' => $userId,
            'outlookUserId' => $outlookUserId,
        ]);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function processCreateUpdateEvent(
        OutlookCalendarUser $calendarUser,
        string $outlookUserId,
        array $params,
        string $eventId,
        stdClass $item,
    ): void {

        $userId = $calendarUser->getUserId();
        $user = $this->getUserById($userId);

        $link = $this->getEventRepo()->getEntityByCalendarIdEventId(
            calendarId: $calendarUser->getCalendarId(),
            eventId: $eventId,
        );

        $itemData = $this->getDataFromItem($item);

        $identLabelMap = $params['labelMap'];

        if (!$link) {
            $this->processCreateUpdateEventNoLink(
                calendarUser: $calendarUser,
                outlookUserId: $outlookUserId,
                params: $params,
                eventId: $eventId,
                item: $item,
            );

            return;
        }

        if (!$link->isPrimary()) {
            return;
        }

        if ($link->isEspoEvent() && $link->isUpdated()) {
            return;
        }

        $entity = $this->getEventRepo()->getEventEntityByCalendarIdEventId(
            calendarId: $calendarUser->getCalendarId(),
            eventId: $eventId,
        );

        if (!$entity instanceof Meeting && !$entity instanceof Call && !$entity instanceof Event) {
            return;
        }

        $link->setIsUpdated(false);
        $link->setSyncedAt(DateTimeValue::createNow());

        $this->entityManager->saveEntity($link);

        if ($link->isEspoEvent() && !$this->aclManager->check($user, $entity, Table::ACTION_EDIT)) {
            $this->log->info("Outlook sync: No access to edit event for user $userId.");

            return;
        }

        if (isset($itemData->name)) {
            $name = $this->getRealNameFromOutlookName($itemData->name, $identLabelMap);
            $name = $this->sanitizeName($entity, $name);

            $entity->set(Field::NAME, $name);
        }

        if ($link->isEspoEvent()) {
            unset($itemData->description);
        }

        unset($itemData->name);

        $entity->setMultiple($itemData);

        $this->entityManager->saveEntity($entity, [
            'isOutlookSync' => true,
            'noNotifications' => true,
            'calendarId' => $calendarUser->getCalendarId(),
        ]);
    }

    private function getRealNameFromOutlookName($name, $identLabelMap)
    {
        foreach ($identLabelMap as $label) {
            if (str_starts_with($name, $label . ':')) {
                $name = trim(substr($name, mb_strlen($label . ':')));

                break;
            }
        }

        return $name;
    }

    /**
     * @throws Error
     */
    public function pushToOutlook(OutlookCalendarUser $calendarUser, ExternalAccount $externalAccount): void
    {
        $userId = $calendarUser->getUserId();

        $entityTypeList = $this->getPushEntityTypes($externalAccount, $this->getUserById($userId));

        $isMain = $calendarUser->getType() === self::CALENDAR_USER_TYPE_MAIN;

        $syncStartDate = $externalAccount->get('calendarStartDate');
        $maxSize = $this->config->get('outlookCalendarPushMaxPortionSize', self::PUSH_PORTION_SIZE);

        $params = [
            'defaultEntityType' => $externalAccount->get('calendarDefaultEntity'),
            'labelMap' => $this->buildIdentityLabelMap($externalAccount),
            'syncAttendees' => $externalAccount->get('calendarSyncAttendees'),
        ];

        $dontPushPastEvents = $externalAccount->get('calendarDontPushPastEvents') ?? false;

        $totalNewCount = 0;

        foreach ($entityTypeList as $entityType) {
            $count = 0;

            $newEntityList = [];
            $updatedEntityList = [];
            $deletedEntityList = [];

            if ($isMain && $count < $maxSize) {
                $newEntityList = $this->getNewEntityListToPush(
                    entityType: $entityType,
                    userId: $userId,
                    syncStartDate: $syncStartDate,
                    maxSize: $maxSize - $count,
                    dontPushPastEvents: $dontPushPastEvents,
                    //outlookUserId: $externalAccount->get('outlookUserId'),
                );

                $count += count($newEntityList);
                $totalNewCount += count($newEntityList);
            }

            if ($count < $maxSize) {
                $updatedEntityList = $this->getUpdatedEntityListToPush(
                    entityType: $entityType,
                    //userId: $userId,
                    calendarId: $calendarUser->getCalendarId(),
                    maxSize: $maxSize - $count,
                );

                $count += count($updatedEntityList);
            }

            if ($count < $maxSize) {
                $deletedEntityList = $this->getDeletedEntityListToPush(
                    entityType: $entityType,
                    //userId: $userId,
                    calendarId: $calendarUser->getCalendarId(),
                    maxSize: $maxSize - $count,
                    includeOutlookEvents: $externalAccount->get('removeOutlookCalendarEventIfRemovedInEspo'),
                );
            }

            [$batchHash, $requestItemList] = $this->pushPrepareRequest(
                entityType: $entityType,
                params: $params,
                calendarUser: $calendarUser,
                newEntityList: $newEntityList,
                updatedEntityList: $updatedEntityList,
                deletedEntityList: $deletedEntityList,
            );

            if (!count($requestItemList)) {
                continue;
            }

            $resultList = $this->getUserClient($userId)->batchRequest($requestItemList);

            if (count($resultList) !== count($batchHash)) {
                throw new RuntimeException("Outlook Calendar sync: Bad batch response. Doesn't match request.");
            }

            $this->processPushResult(
                resultList: $resultList,
                batchHash: $batchHash,
                calendarUser: $calendarUser,
                externalAccount: $externalAccount,
            );

            foreach ($batchHash as $item) {
                if ($item['type'] === 'PATCH') {
                    $item['eventEntity']->set('isUpdated', false);

                    $this->entityManager->saveEntity($item['eventEntity']);
                }

                if ($item['type'] === 'DELETE') {
                    $this->entityManager->removeEntity($item['eventEntity']);
                }
            }
        }

        if ($dontPushPastEvents || $totalNewCount !== 0) {
            return;
        }

        $this->updateExternalAccountAfterPush($externalAccount);
    }

    /**
     * Pushed only for the assigned user. Only records that are not synced for any user.
     *
     * @return iterable<Meeting|Call|Event>
     */
    private function getNewEntityListToPush(
        string $entityType,
        string $userId,
        string $syncStartDate,
        int $maxSize,
        bool $dontPushPastEvents = false,
        //?string $outlookUserId = null,
    ): iterable {

        $user = $this->getUserById($userId);

        try {
            $builder = $this->selectBuilderFactory
                ->create()
                ->from($entityType)
                ->forUser($user)
                ->withStrictAccessControl()
                ->buildQueryBuilder();
        } catch (BadRequest|Forbidden $e) {
            throw new RuntimeException($e->getMessage());
        }

        $builder->where(['status!=' => Meeting::STATUS_NOT_HELD]);

        if (!$dontPushPastEvents) {
            $builder->where(['dateStart>=' => $syncStartDate]);
        } else {
            $since = new DateTime();

            try {
                $since->modify('-' . $this->config->get('outlookCalendarPushPastPeriod', '5 days'));
            } catch (Exception $e) {
                throw new RuntimeException($e->getMessage(), 0, $e);
            }

            $builder->where([
                'createdAt>=' => $since->format(DateTimeUtil::SYSTEM_DATE_TIME_FORMAT),
            ]);
        }

        $joinConditions = [
            'e.entityId:' => 'id',
            'e.entityType' => $entityType,
            'e.deleted' => false,
            //'e.userId' => $userId,
        ];

        /*if ($outlookUserId) {
            $joinConditions['OR'] = [
                ['e.outlookUserId' => null],
                ['e.outlookUserId' => $outlookUserId],
            ];
        }*/

        $builder
            ->leftJoin(OutlookCalendarEvent::ENTITY_TYPE, 'e', $joinConditions)
            /*->leftJoin('OutlookCalendarEvent', 'eOther', [
                'eOther.entityId:' => 'id',
                'eOther.entityType' => $entityType,
                'eOther.deleted' => false,
                'eOther.isEspoEvent' => false,
            ])
            ->distinct()*/
            ->where([
                'e.id' => null,
                //'eOther.id' => null,
            ]);

        if ($entityType === Meeting::ENTITY_TYPE) {
            $builder->where(['outlookSkipPush' => false]);
        }

        /*if ($seed->hasRelation('users')) {
            $builder
                ->join('users')
                ->distinct()
                ->where(['usersMiddle.userId' => $userId]);
        } else */{
            $builder->where(['assignedUserId' => $userId]);
        }

        /** @var iterable<Meeting|Call|Event> */
        return $this->entityManager
            ->getRDBRepository($entityType)
            ->clone($builder->build())
            ->limit(0, $maxSize)
            ->order('modifiedAt')
            ->find();
    }

    /**
     * @return iterable<Meeting|Call|Event>
     */
    private function getUpdatedEntityListToPush(
        string $entityType,
        //string $userId,
        string $calendarId,
        int $maxSize
    ) {
        /*
          $user = $this->getUserById($userId);

          try {
            $builder = $this->selectBuilderFactory
                ->create()
                ->from($entityType)
                ->forUser($user)
                ->withStrictAccessControl()
                ->buildQueryBuilder();
        } catch (BadRequest|Forbidden $e) {
            throw new RuntimeException($e->getMessage());
        }*/

        $builder = SelectBuilder::create()
            ->from($entityType);

        $builder
            ->join(OutlookCalendarEvent::ENTITY_TYPE, 'e', [
                'e.entityId:' => 'id',
                'e.entityType' => $entityType,
                'e.deleted' => false,
                'e.calendarId' => $calendarId,
            ])
            ->distinct()
            ->where([
                'e.isUpdated' => true,
                'e.isDeleted' => false,
            ]);

        /*
        $seed = $this->entityManager->getNewEntity($entityType);

        if ($seed->hasRelation('users')) {
            $builder
                ->join('users')
                ->distinct()
                ->where(['usersMiddle.userId' => $userId]);
        } else if ($seed->hasAttribute('assignedUserId')) {
            $builder->where(['assignedUserId' => $userId]);
        } else {
            $this->log->warning("Outlook Calendar sync: No user relationship for $entityType.");

            return [];
        }*/

        /** @var iterable<Meeting|Call|Event> */
        return $this->entityManager
            ->getRDBRepository($entityType)
            ->clone($builder->build())
            ->limit(0, $maxSize)
            ->order('modifiedAt')
            ->find();
    }

    /**
     * @return iterable<Meeting|Call|Event>
     */
    private function getDeletedEntityListToPush(
        string $entityType,
        //string $userId,
        string $calendarId,
        int $maxSize,
        bool $includeOutlookEvents = false
    ) {
        /*
         $user = $this->getUserById($userId);

         try {
            $builder = $this->selectBuilderFactory
                ->create()
                ->from($entityType)
                ->forUser($user)
                ->withStrictAccessControl()
                ->buildQueryBuilder();
        } catch (BadRequest|Forbidden $e) {
            throw new RuntimeException($e->getMessage());
        }*/

        $builder = SelectBuilder::create()
            ->from($entityType);

        $builder
            ->join(OutlookCalendarEvent::ENTITY_TYPE, 'e', [
                'e.entityId:' => 'id',
                'e.entityType' => $entityType,
                'e.deleted' => false,
                'e.calendarId' => $calendarId,
            ])
            ->distinct()
            ->where(['e.isDeleted' => true]);

        if (!$includeOutlookEvents) {
            $builder->where(['e.isEspoEvent' => true]);
        }

        /*
         $seed = $this->entityManager->getNewEntity($entityType);

         if ($seed->hasRelation('users')) {
            $builder
                ->join('users')
                ->distinct()
                ->where(['usersMiddle.userId' => $userId]);
        } else if ($seed->hasAttribute('assignedUserId')) {
            $builder->where(['assignedUserId' => $userId]);
        } else {
            $this->log->warning("Outlook Calendar sync: No user relationship for $entityType.");

            return [];
        }*/

        $builder->withDeleted();

        /** @var iterable<Meeting|Call|Event> */
        return $this->entityManager
            ->getRDBRepository($entityType)
            ->clone($builder->build())
            ->limit(0, $maxSize)
            ->order('modifiedAt')
            ->find();
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function getItemFromEntity(
        array $params,
        Entity $entity,
        bool $isEspoEvent = false,
    ): array {

        return $this->itemPreparator->prepare($entity, $isEspoEvent, $params);
    }

    /**
     * @param stdClass $item
     * @return object{
     *     dateStart: string,
     *     dateEnd: string,
     *     dateStartDate?: string,
     *     dateEndDate?: string,
     *     isAllDay?: bool,
     *     name?: string,
     *     description?: string,
     *     joinUrl?: string,
     *     isPrivate: bool,
     *     location?: ?string,
     *     isCancelled: ?bool,
     *     isOrganizer: ?bool,
     * }
     */
    private function getDataFromItem($item)
    {
        $isAllDay = $item->IsAllDay ?? $item->isAllDay ?? null;

        $dateStart = null;
        $dateEnd = null;

        $itemStart = $item->Start ?? $item->start ?? null;
        $itemEnd = $item->End ?? $item->end ?? null;

        if (isset($itemStart)) {
            try {
                $start = new DateTime($itemStart['DateTime'] ?? $itemStart['dateTime']);
            } catch (Exception $e) {
                throw new RuntimeException($e->getMessage());
            }

            $dateStart = $start->format(DateTimeUtil::SYSTEM_DATE_TIME_FORMAT);
        }

        if (isset($itemEnd)) {
            try {
                $end = new DateTime($itemEnd['DateTime'] ?? $itemEnd['dateTime']);
            } catch (Exception $e) {
                throw new RuntimeException($e->getMessage());
            }

            $dateEnd = $end->format(DateTimeUtil::SYSTEM_DATE_TIME_FORMAT);
        }

        if ($isAllDay && isset($start) && isset($end)) {
            $dateStartDate = $start->format(DateTimeUtil::SYSTEM_DATE_FORMAT);

            try {
                $dateEndDate = $end->modify('-1 day')->format(DateTimeUtil::SYSTEM_DATE_FORMAT);
            } catch (Exception $e) {
                throw new RuntimeException($e->getMessage(), 0, $e);
            }
        }

        $name = $item->Subject ?? $item->subject ?? null;

        $description = null;

        $body = $item->Body ?? $item->body ?? null;

        if ($body) {
            $bodyContentType = $body['ContentType'] ?? $body['contentType'] ?? null;

            if (strtolower($bodyContentType) === 'html') {
                $description = self::htmlToPlainText($body['Content'] ?? $body['content'] ?? '') ?? null;
            } else if (strtolower($bodyContentType) === 'text') {
                $description = $body['Content'] ?? $body['content'] ?? null;
            }
        }

        $data = (object) [
            'isCancelled' => $item->isCancelled ?? $item->IsCancelled ?? null,
        ];

        if ($dateStart) {
            $data->dateStart = $dateStart;
        }

        if ($dateEnd) {
            $data->dateEnd = $dateEnd;
        }

        if (!is_null($isAllDay)) {
            $data->isAllDay = $isAllDay;
        }

        if (!is_null($name)) {
            $data->name = $name;
        }

        if (!is_null($description)) {
            $description = trim($description) ?? null;
            $data->description = $description;
        }

        if ($isAllDay) {
            if (isset($dateStartDate) && $dateStartDate) {
                $data->dateStartDate = $dateStartDate;
            }

            if (isset($dateEndDate) && $dateEndDate) {
                $data->dateEndDate = $dateEndDate;
            }
        }

        $sensitivity = strtolower($item->Sensitivity ?? $item->sensitivity ?? '');

        $data->isPrivate = $sensitivity === 'private';

        // Microsoft does not expose location.locationUri
        if (isset($item->onlineMeeting) && is_array($item->onlineMeeting)) {
            if (isset($item->onlineMeeting['joinUrl']) && is_string($item->onlineMeeting['joinUrl'])) {
                $data->joinUrl = $item->onlineMeeting['joinUrl'];
            }
        }

        if (isset($item->location) && is_array($item->location)) {
            if (isset($item->location['displayName'])) {
                $data->location = $item->location['displayName'];
            }
        }

        $data->isOrganizer = $item->isOrganizer ?? null;

        return $data;
    }

    private static function htmlToPlainText($body)
    {
        $breaks = ["<br />","<br>","<br/>","<br />","&lt;br /&gt;","&lt;br/&gt;","&lt;br&gt;"];

        $body = str_ireplace($breaks, "\r\n", $body);
        $body = strip_tags($body);

        $reList = [
            '/&(quot|#34);/i',
            '/&(amp|#38);/i',
            '/&(lt|#60);/i',
            '/&(gt|#62);/i',
            '/&(nbsp|#160);/i',
            '/&(iexcl|#161);/i',
            '/&(cent|#162);/i',
            '/&(pound|#163);/i',
            '/&(copy|#169);/i',
            '/&(reg|#174);/i',
        ];

        $replaceList = [
            '',
            '&',
            '<',
            '>',
            ' ',
            chr(161),
            chr(162),
            chr(163),
            chr(169),
            chr(174)
        ];

        return preg_replace($reList, $replaceList, $body);
    }

    private function setEspoLocation(Entity $entity, ?string $location): void
    {
        $maxLength = $entity->getAttributeParam('cLocation', 'len') ?? 255;

        if (
            !$entity->hasAttribute('cLocation') ||
            $location && strlen($location) > $maxLength
        ) {
            return;
        }

        $entity->set('cLocation', $location);
    }

    /**
     * @param array<string, mixed> $params
     * @param iterable<int, Entity> $newEntityList
     * @param iterable<int, Entity> $updatedEntityList
     * @param iterable<int, Entity> $deletedEntityList
     * @return array{0: array<string, array<string, mixed>>, 1: stdClass[]}
     *
     */
    private function pushPrepareRequest(
        string $entityType,
        array $params,
        OutlookCalendarUser $calendarUser,
        iterable $newEntityList,
        iterable $updatedEntityList,
        iterable $deletedEntityList,
    ): array  {

        /** @var array<string, array{entity: Entity, type: string, eventEntity?: Entity}> $batchHash */
        $batchHash = [];
        $requestItemList = [];
        $counter = 1;

        foreach ($newEntityList as $entity) {
            $item = $this->getItemFromEntity($params, $entity, true);

            $requestItemList[] = (object) [
                'id' => strval($counter),
                'method' => 'POST',
                'url' => '/me/calendars/' . $calendarUser->getOutlookCalendarId() . '/events',
                'headers' => (object)[
                    'Content-Type' => 'application/json',
                ],
                'body' => $item,
            ];

            $batchHash[strval($counter)] = [
                'type' => 'POST',
                'entity' => $entity,
            ];

            $counter++;
        }

        foreach ($updatedEntityList as $entity) {
            $eventEntity = $this->entityManager
                ->getRDBRepositoryByClass(OutlookCalendarEvent::class)
                ->where([
                    'entityId' => $entity->getId(),
                    'entityType' => $entityType,
                    'calendarId' => $calendarUser->getCalendarId(),
                ])
                ->findOne();

            if (!$eventEntity) {
                continue;
            }

            $item = $this->getItemFromEntity($params, $entity, $eventEntity->isEspoEvent());

            $eventId = $eventEntity->getEventId();

            $requestItemList[] = (object) [
                'id' => strval($counter),
                'method' => 'PATCH',
                'url' => '/me/calendars/' . $calendarUser->getOutlookCalendarId() . '/events/' . $eventId,
                'headers' => (object)[
                    'Content-Type' => 'application/json',
                ],
                'body' => $item,
            ];

            $batchHash[strval($counter)] = [
                'type' => 'PATCH',
                'entity' => $entity,
                'eventEntity' => $eventEntity,
            ];

            $counter++;
        }

        foreach ($deletedEntityList as $entity) {
            $eventEntity = $this->entityManager
                ->getRDBRepositoryByClass(OutlookCalendarEvent::class)
                ->where([
                    'entityId' => $entity->getId(),
                    'entityType' => $entityType,
                    'calendarId' => $calendarUser->getCalendarId(),
                ])
                ->findOne();

            if (!$eventEntity) {
                continue;
            }

            $eventId = $eventEntity->getEventId();

            $requestItemList[] = (object) [
                'id' => strval($counter),
                'method' => 'DELETE',
                'url' => '/me/calendars/' . $calendarUser->getOutlookCalendarId() . '/events/' . $eventId,
            ];

            $batchHash[strval($counter)] = [
                'type' => 'DELETE',
                'entity' => $entity,
                'eventEntity' => $eventEntity,
            ];

            $counter++;
        }

        return [$batchHash, $requestItemList];
    }

    /**
     * @param iterable<int, array<string, mixed>> $resultList
     * @param array<string, array<string, mixed>> $batchHash
     */
    private function processPushResult(
        iterable $resultList,
        array $batchHash,
        OutlookCalendarUser $calendarUser,
        ExternalAccount $externalAccount,
    ): void {

        foreach ($resultList as $item) {
            $id = $item['id'] ?? null;

            if (!$id) {
                $this->log->warning("Outlook Calendar sync: No ID in batch response item.");

                continue;
            }

            $requestItem = $batchHash[$id] ?? null;

            if (!$requestItem) {
                $this->log->warning("Outlook Calendar sync: Bad ID in batch response item.");

                continue;
            }

            if ($requestItem['type'] === 'POST' && $item['status'] === 201) {
                $responseData = $item['body'];

                /** @var Entity $itemEntity */
                $itemEntity = $requestItem['entity'];

                if (!$responseData) {
                    continue;
                }

                $this->syncHelper->processAfterPush(
                    entity: $itemEntity,
                    calendarUser: $calendarUser,
                    externalAccount: $externalAccount,
                    response: $responseData,
                );
            }
        }
    }

    private function updateExternalAccountAfterPush(ExternalAccount $externalAccount): void
    {
        $externalAccount->set('calendarDontPushPastEvents', true);

        $externalAccountCopy = $this->entityManager->getEntityById('ExternalAccount', $externalAccount->getId());
        $externalAccountCopy->set('calendarDontPushPastEvents', true);

        $this->entityManager->saveEntity($externalAccountCopy);
    }

    /**
     * @return string[]
     */
    private function getPushEntityTypes(ExternalAccount $externalAccount, User $user): array
    {
        $entityTypeList = [];

        foreach ($externalAccount->get('calendarEntityTypes') ?? [] as $entityType1) {
            if (!$this->aclManager->check($user, $entityType1, Table::ACTION_READ)) {
                continue;
            }

            if (!$this->entityManager->hasRepository($entityType1)) {
                continue;
            }

            $entityTypeList[] = $entityType1;
        }

        return $entityTypeList;
    }


    private function getOutlookCalendarEventByICalUid(string $iCalUId): ?OutlookCalendarEvent
    {
        return $this->entityManager
            ->getRDBRepositoryByClass(OutlookCalendarEvent::class)
            ->where([
                OutlookCalendarEvent::FIELD_I_CAL_UID => $iCalUId,
            ])
            ->findOne();
    }

    private function getEntityForOutlookCalendarEvent(OutlookCalendarEvent $altLink): Meeting|Call|Event|null
    {
        if (!$altLink->getTargetEntityId() || !$altLink->getTargetEntityType()) {
            return null;
        }

        $entity = $this->entityManager->getEntityById($altLink->getTargetEntityType(), $altLink->getTargetEntityId());

        if (
            !$entity instanceof Meeting &&
            !$entity instanceof Call &&
            !$entity instanceof Event
        ) {
            return null;
        }

        return $entity;
    }

    private function sanitizeName(Event|Meeting|Call $entity, ?string $name): ?string
    {
        if (!$name) {
            return null;
        }

        $maxLength = $entity->getAttributeParam(Field::NAME, 'len');

        if ($maxLength && mb_strlen($name) > $maxLength) {
            $name = mb_substr($name, 0, $maxLength);
        }

        return $name;
    }

    /**
     * @param stdClass $item
     * @param array{
     *      defaultEntity: string,
     *      labelMap: array<string, string>,
     *      skipPrivate: bool,
     *      createContacts: bool,
     *  } $params
     */
    private function populateEntityWithItemData(
        Event|Meeting|Call $entity,
        stdClass $itemData,
        ?string $userId,
        ?string $iCalUId,
        stdClass $item,
        User $user,
        array $params,
    ): void {

        $name = $this->getRealNameFromOutlookName($itemData->name ?? '', $params['labelMap']);
        $location = $itemData->location ?? null;
        $joinUrl = $itemData->joinUrl ?? null;

        $entity->setMultiple($itemData);
        $entity->set(Field::ASSIGNED_USER . 'Id', $userId);

        if (
            ($entity instanceof Meeting || $entity instanceof Call) &&
            $iCalUId && strlen($iCalUId) <= 255
        ) {
            $entity->setUid($iCalUId);
        }

        $this->setEspoLocation($entity, $location);

        if ($joinUrl && $entity instanceof Meeting) {
            $entity->setJoinUrl($joinUrl);
        }

        $entity->set(Field::NAME, $this->sanitizeName($entity, $name));

        $attendeeList = $item->attendees ?? $item->Attendees ?? [];

        if (!$attendeeList) {
            return;
        }

        $accountId = null;
        $leadId = null;
        $contactId = null;

        foreach ($attendeeList as $attendeeItem) {
            $emailAddress = $attendeeItem['emailAddress']['address'] ??
                $attendeeItem['EmailAddress']['Address'] ?? null;

            $attendeeName = $attendeeItem['emailAddress']['name'] ?? $attendeeItem['EmailAddress']['Name'] ?? null;

            if (!is_string($emailAddress)) {
                continue;
            }

            $foundAttendee = $this->entityLookup->findOne($emailAddress, [
                Contact::ENTITY_TYPE,
                Lead::ENTITY_TYPE,
                User::ENTITY_TYPE,
                Account::ENTITY_TYPE,
            ]);

            if ($foundAttendee) {
                if ($foundAttendee instanceof Contact) {
                    if ($entity->hasLinkMultipleField('contacts')) {
                        $entity->addLinkMultipleId('contacts', $foundAttendee->getId());
                    }

                    if (!$contactId) {
                        $contactId = $foundAttendee->getId();
                    }

                    if (!$accountId && $foundAttendee->getAccount()?->getId()) {
                        $accountId = $foundAttendee->getAccount()?->getId();
                    }
                } else if ($foundAttendee instanceof Lead) {
                    if ($entity->hasLinkMultipleField('leads')) {
                        $entity->addLinkMultipleId('leads', $foundAttendee->getId());
                    }

                    if (!$leadId) {
                        $leadId = $foundAttendee->getId();
                    }
                } else if ($foundAttendee instanceof Account) {
                    $accountId = $foundAttendee->getId();
                } else if ($foundAttendee->getEntityType() === User::ENTITY_TYPE) {
                    if (
                        $foundAttendee->getId() !== $userId &&
                        !in_array($foundAttendee->getId(), $this->getUserWithFetchIntegrationIdList()) &&
                        $entity->hasLinkMultipleField('users')
                    ) {
                        $entity->addLinkMultipleId('users', $foundAttendee->getId());
                    }
                }

                continue;
            }

            if (
                !$params['createContacts'] ||
                !$this->aclManager->check($user, Contact::ENTITY_TYPE, Table::ACTION_CREATE)
            ) {
                continue;
            }

            $firstName = null;
            $lastName = null;

            if ($attendeeName) {
                $lastName = $attendeeName;

                if ($sIndex = mb_strpos($attendeeName, ' ')) {
                    $firstName = trim(mb_substr($attendeeName, 0, $sIndex));
                    $lastName = trim(mb_substr($attendeeName, $sIndex + 1));
                }
            }

            $contact = $this->entityManager->getRDBRepositoryByClass(Contact::class)->getNew();

            $contact->setMultiple([
                'firstName' => $firstName,
                'lastName' => $lastName,
                Field::EMAIL_ADDRESS => $emailAddress,
                'assignedUserId' => $user->getId(),
            ]);

            if ($user->getDefaultTeam()?->getId()) {
                $contact->addLinkMultipleId(Field::TEAMS, $user->getDefaultTeam()?->getId());
            }

            $this->entityManager->saveEntity($contact);

            $contactId = $contact->getId();

            if ($entity->hasLinkMultipleField('contacts')) {
                $entity->addLinkMultipleId('contacts', $contactId);
            }
        }

        if ($accountId) {
            $entity->set('accountId', $accountId);
            $entity->set('parentId', $accountId);
            $entity->set('parentType', Account::ENTITY_TYPE);
        } else if ($leadId) {
            $entity->set('parentId', $leadId);
            $entity->set('parentType', Lead::ENTITY_TYPE);
        } else if ($contactId) {
            $entity->set('parentId', $contactId);
            $entity->set('parentType', Contact::ENTITY_TYPE);
        }
    }


    private function updateEntityOrganizer(
        Event|Meeting|Call $entity,
        ?string $userId,
        bool &$skipSave,
        stdClass $itemData,
    ): void  {

        if (!$entity->hasLinkMultipleField('users')) {
            return;
        }

        $this->entityManager
            ->getRelation($entity, 'users')
            ->relateById($userId);

        $skipSave = true;

        if (
            $entity->get(Field::ASSIGNED_USER . 'Id') !== $userId &&
            $itemData->isOrganizer &&
            !$this->getEventRepo()->isEspoEventForAssignee($entity)
        ) {
            $entity->set(Field::ASSIGNED_USER . 'Id', $userId);

            $this->entityManager->saveEntity($entity, [
                'skipOutlookSync' => true,
                'noNotifications' => true,
            ]);
        }
    }

    /**
     * @param Exception $exception
     * @param OutlookCalendarUser $calendarUser
     * @return array<string, mixed>
     * @throws ApiError
     * @throws Error
     */
    private function fetchFromOutlookAfterException(
        Exception $exception,
        OutlookCalendarUser $calendarUser,
        ?string $userId,
        string $nowString,
        string $end,
        ?int $synMaxPageSize
    ): array  {

        $reRun = false;

        if ($exception instanceof ApiError && $calendarUser->getDeltaToken()) {
            $result = $exception->getResult();
            $errorCode = $exception->getOriginalCode();

            if ($errorCode === 400 && strtolower($result['message']) === strtolower('Badly formed token.')) {
                $this->log->warning(
                    "Outlook sync: Delta token is not accepted. " .
                    "Syncing from now to obtain new delta token. " .
                    "User: " . $userId . ". " .
                    "Calendar ID: " . $calendarUser->getOutlookCalendarId() . "."
                );

                $reRun = true;
            }
        }

        if (!$reRun) {
            throw $exception;
        }

        return $this->getUserClient($userId)->requestSync($calendarUser->getOutlookCalendarId(), [
            'start' => $nowString,
            'end' => $end,
            'maxPageSize' => $synMaxPageSize,
            'deltaToken' => null,
            'skipToken' => null,
        ]);
    }
}
