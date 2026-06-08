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

namespace Espo\Modules\Outlook\Tools\Contacts\Jobs;

use DateTime;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Job\Job;
use Espo\Core\Job\Job\Data;
use Espo\Core\Job\JobSchedulerFactory;
use Espo\Core\Select\SelectBuilderFactory;
use Espo\Core\Select\Where\Item;
use Espo\Core\Utils\Log;
use Espo\Entities\ExternalAccount;
use Espo\Entities\User;
use Espo\Modules\Outlook\Core\Outlook\ContactsManager;
use Espo\ORM\EntityManager;
use RuntimeException;

class PushPortion implements Job
{
    private EntityManager $entityManager;
    private ContactsManager $contactsManager;
    private SelectBuilderFactory $selectBuilderFactory;
    private Log $log;
    private JobSchedulerFactory $jobSchedulerFactory;

    public function __construct(
        EntityManager $entityManager,
        ContactsManager $contactsManager,
        SelectBuilderFactory $selectBuilderFactory,
        Log $log,
        JobSchedulerFactory $jobSchedulerFactory
    ) {
        $this->entityManager = $entityManager;
        $this->contactsManager = $contactsManager;
        $this->selectBuilderFactory = $selectBuilderFactory;
        $this->log = $log;
        $this->jobSchedulerFactory = $jobSchedulerFactory;
    }

    public function run(Data $data): void
    {
        $data = $data->getRaw();

        $integrationEntity = $this->entityManager->getEntityById('Integration', 'Outlook');

        if (
            !$integrationEntity ||
            !$integrationEntity->get('enabled')
        ) {

            $this->log->error('Outlook Contacts Pushing : Integration Disabled');

            throw new RuntimeException();
        }

        $userId = $data->userId;
        $entityType = $data->entityType;
        $ids = $data->ids;

        /** @var ?ExternalAccount $externalAccount */
        $externalAccount = $this->entityManager->getEntityById('ExternalAccount', 'Outlook__' . $userId);

        if (
            !$externalAccount ||
            !$externalAccount->get('enabled') ||
            !$externalAccount->get('outlookContactsEnabled')
        ) {
            $this->log->error('Outlook Contacts Pushing : Integration Disabled for User ' . $userId);

            throw new RuntimeException();
        }

        $where = [
            [
                'type' => 'in',
                'field' => 'id',
                'value' => $ids,
            ]
        ];

        /** @var ?User $user */
        $user = $this->entityManager->getEntityById('User', $userId);

        if (!$user) {
            throw new RuntimeException("No user.");
        }

        try {
            $query = $this->selectBuilderFactory
                ->create()
                ->from($entityType)
                ->forUser($user)
                ->withStrictAccessControl()
                ->withWhere(
                    Item::fromRawAndGroup($where)
                )
                ->build();
        }
        catch (BadRequest|Error|Forbidden $e) {
            throw new RuntimeException($e->getMessage());
        }

        $collection = $this->entityManager
            ->getRDBRepository($entityType)
            ->clone($query)
            ->find();

        $result = $this->contactsManager->pushContacts($collection, $userId, $externalAccount);

        if (!count($result->leftIdList)) {
            return;
        }

        $time = new DateTime();

        $time->modify('+60 seconds');

        $this->jobSchedulerFactory
            ->create()
            ->setClassName(PushPortion::class)
            ->setTime($time)
            ->setData([
                'ids' => $result->leftIdList,
                'userId' => $userId,
                'entityType' => $entityType,
            ])
            ->schedule();
    }
}
