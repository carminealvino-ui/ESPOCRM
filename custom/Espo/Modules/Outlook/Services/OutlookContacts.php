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

namespace Espo\Modules\Outlook\Services;

use Espo\Core\Acl;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;

use DateTime;
use Espo\Core\Job\JobSchedulerFactory;
use Espo\Core\Select\SelectBuilderFactory;
use Espo\Core\Select\Where\Item;
use Espo\Core\Utils\Config;
use Espo\Entities\ExternalAccount;
use Espo\Entities\User;
use Espo\Modules\Outlook\Core\Outlook\ContactsManager;
use Espo\Modules\Outlook\Tools\Contacts\Jobs\PushPortion;
use Espo\ORM\EntityManager;

class OutlookContacts
{
    const PUSH_PORTION_SIZE = 5;

    private EntityManager $entityManager;
    private User $user;
    private ContactsManager $contactsManager;
    private Acl $acl;
    private Config $config;
    private SelectBuilderFactory $selectBuilderFactory;
    private JobSchedulerFactory $jobSchedulerFactory;

    public function __construct(
        EntityManager $entityManager,
        User $user,
        ContactsManager $contactsManager,
        Acl $acl,
        Config $config,
        SelectBuilderFactory $selectBuilderFactory,
        JobSchedulerFactory $jobSchedulerFactory
    ) {
        $this->entityManager = $entityManager;
        $this->user = $user;
        $this->contactsManager = $contactsManager;
        $this->acl = $acl;
        $this->config = $config;
        $this->selectBuilderFactory = $selectBuilderFactory;
        $this->jobSchedulerFactory = $jobSchedulerFactory;
    }

    /**
     * @return array
     */
    public function contactFolders()
    {
        return $this->contactsManager->getContactFolderList($this->user->get('id'));
    }

    /**
     * @throws BadRequest
     * @throws Forbidden
     * @throws Error
     */
    public function push(string $entityType, array $params): int
    {
        $integrationEntity = $this->entityManager->getEntityById('Integration', 'Outlook');

        if (
            !$integrationEntity ||
            !$integrationEntity->get('enabled')
        ) {
            throw new Forbidden();
        }

        if (!$this->acl->checkScope('OutlookContacts')) {
            throw new Forbidden();
        }

        $userId = $this->user->get('id');

        /** @var ?ExternalAccount $externalAccount */
        $externalAccount = $this->entityManager->getEntityById('ExternalAccount', 'Outlook__' . $userId);

        if (!$externalAccount->get('enabled') || !$externalAccount->get('outlookContactsEnabled')) {
            throw new Forbidden();
        }

        $portion = $this->config->get('outlookContactsPushPortionSize', self::PUSH_PORTION_SIZE);

        $resultCount = 0;

        if (array_key_exists('ids', $params)) {
            $ids = $params['ids'];

            $where = [
                [
                    'type' => 'in',
                    'field' => 'id',
                    'value' => $ids
                ]
            ];
        }
        else if (array_key_exists('where', $params)) {
            $where = $params['where'];
        }
        else {
            throw new BadRequest();
        }

        $builder = $this->selectBuilderFactory
            ->create()
            ->from($entityType)
            ->withStrictAccessControl()
            ->withWhere(
                Item::fromRawAndGroup($where)
            )
            ->buildQueryBuilder();

        $total = $this->entityManager
            ->getRDBRepository($entityType)
            ->clone($builder->build())
            ->count();

        if (!$total || !$portion) {
            return 0;
        }

        $runNow = true;
        $offset = 0;
        $result = null;
        $time = new DateTime();

        while ($offset <= $total) {
            $builder->limit($offset, $portion);

            $collection = $this->entityManager
                ->getRDBRepository($entityType)
                ->clone($builder->build())
                ->find();

            if ($runNow) {
                $result = $this->contactsManager->pushContacts($collection, $userId, $externalAccount);

                $resultCount += $result->count;

                $runNow = false;
            }
            else {
                $ids = [];

                foreach ($collection as $entity) {
                    $ids[] = $entity->get('id');
                }

                $data = [
                    'ids' => $ids,
                    'userId' => $userId,
                    'entityType' => $entityType,
                ];

                $time->modify('+30 seconds');

                $this->jobSchedulerFactory
                    ->create()
                    ->setClassName(PushPortion::class)
                    ->setTime($time)
                    ->setData($data)
                    ->schedule();
            }

            $offset += $portion;
        }

        if ($result && count($result->leftIdList)) {
            $time->modify('+30 seconds');

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

        return $resultCount;
    }
}
