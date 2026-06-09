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

use Espo\Core\ExternalAccount\ClientManager;
use Espo\Entities\ExternalAccount;
use Espo\Modules\Outlook\Core\Outlook\Clients\Contacts;
use Espo\Modules\Outlook\Core\Outlook\Clients\Outlook;
use Espo\ORM\EntityManager;
use RuntimeException;

class ContactsManager
{
    private $userClientMap = [];
    private ClientManager $clientManager;
    private EntityManager $entityManager;

    public function __construct(
        ClientManager $clientManager,
        EntityManager $entityManager
    ) {
        $this->clientManager = $clientManager;
        $this->entityManager = $entityManager;
    }

    protected function getUserClient(string $userId): Contacts
    {
        if (!array_key_exists($userId, $this->userClientMap)) {
            /** @var Outlook $client */
            $client = $this->clientManager->create('Outlook', $userId);

            $this->userClientMap[$userId] = $client->getContactsClient();
        }

        return $this->userClientMap[$userId];
    }

    public function getContactFolderList($userId, $params = [])
    {
        $list = [];
        $response = $this->getUserClient($userId)->getContactFolderList($params);

        if (is_array($response) && isset($response['value'])) {
            foreach ($response['value'] as $item) {
                $list[] = [
                    'id' => $item['Id'] ?? $item['id'] ,
                    'name' => $item['DisplayName'] ?? $item['displayName'],
                ];
            }
         }

         return $list;
    }

    public function pushContacts($collection, string $userId, ExternalAccount $externalAccount)
    {
        $folderId = $externalAccount->get('contactFolderId') ?? null;

        $outlookUserId = $externalAccount->get('outlookUserId');

        $this->getUserClient($userId)->ping();

        $count = 0;

        $dataList = [];

        foreach ($collection as $entity) {
            $item = [];

            $relationEntity = $this->entityManager
                ->getRDBRepository('OutlookContactsEntity')
                ->where([
                    'entityId' => $entity->get('id'),
                    'entityType' => $entity->getEntityType(),
                    'outlookUserId' => $outlookUserId,
                ])
                ->findOne();

            $item['toUpdate'] = !!$relationEntity;

            if ($relationEntity) {
                $item['relationEntity'] = $relationEntity;
            }

            if ($relationEntity) {
                $item['contactId'] = $relationEntity->get('contactId');
            }

            if (!$relationEntity) {
                $item['contactFolderId'] = $folderId;
            }

            $item['entity'] = $entity;

            $dataList[] = $item;
        }

        $batchHash = [];
        $requestItemList = [];
        $counter = 1;

        foreach ($dataList as $item) {
            $entity = $item['entity'];

            $payloadItem = (object) [
                'GivenName' => $entity->get('firstName'),
                'Surname' => $entity->get('lastName'),
            ];

            if ($entity->get('accountName')) {
                $payloadItem->CompanyName = $entity->get('accountName');
            }

            if ($entity->get('emailAddress')) {
                $payloadItem->EmailAddresses = [
                    [
                        'Address' => $entity->get('emailAddress'),
                        'Name' => $entity->get('name'),
                    ]
                ];
            }

            if ($entity->get('phoneNumber')) {
                $payloadItem->BusinessPhones = [
                    $entity->get('phoneNumber')
                ];
            }

            if (!$item['toUpdate']) {
                if (empty($item['contactFolderId'])) {
                    $url = 'contacts';
                } else {
                    $url = "contactFolders('" . $item['contactFolderId'] ."')/contacts";
                }

                $requestItemList[] = (object) [
                    'id' => strval($counter),
                    'method' => 'POST',
                    'url' => '/me/' . $url,
                    'headers' => (object) [
                        'Content-Type' => 'application/json',
                    ],
                    'body' => $payloadItem,
                ];

                $batchHash[strval($counter)] = [
                    'type' => 'POST',
                    'entity' => $item['entity'],
                ];
            }
            else {
                $url = 'contacts/' . $item['contactId'];

                $requestItemList[] = (object) [
                    'id' => strval($counter),
                    'method' => 'PATCH',
                    'url' => '/me/' . $url,
                    'headers' => (object) [
                        'Content-Type' => 'application/json',
                    ],
                    'body' => $payloadItem,
                ];

                $batchHash[strval($counter)] = [
                    'type' => 'PATCH',
                    'entity' => $item['entity'],
                    'relationEntity' => $item['relationEntity'],
                ];
            }

            $counter++;
        }

        if (!count($requestItemList)) {
            return (object) [
                'count' => 0,
                'leftIdList' => [],
            ];
        }

        $resultList = $this->getUserClient($userId)->batchRequest($requestItemList);

        $leftIdList = [];

        //$reCreateList = [];

        if (count($resultList) !== count($requestItemList)) {
            throw new RuntimeException("Outlook Contacts sync: Bad batch response. Doesn't match request.");
        }

        foreach ($resultList as $item) {
            $id = $item['id'] ?? null;

            if (!$id) {
                $GLOBALS['log']->warning("Outlook Contacts sync: No ID in batch response item.");

                continue;
            }

            $requestItem = $batchHash[$id] ?? null;

            if (!$requestItem) {
                $GLOBALS['log']->warning("Outlook Contacts sync: Bad ID in batch response item.");

                continue;
            }

            if ($item['status'] === 429) {
                $leftIdList[] = $requestItem['entity']->get('id');

                continue;
            }

            if ($requestItem['type'] === 'POST') {
                if ($item['status'] === 201) {
                    $count++;

                    $responseData = $item['body'] ?? null;

                    if (!$responseData) {
                        $GLOBALS['log']->warning("Outlook Contacts sync: No body returned.");

                        continue;
                    }

                    if (isset($responseData['id'])) {
                        $this->entityManager->createEntity('OutlookContactsEntity', [
                            'entityId' => $requestItem['entity']->get('id'),
                            'entityType' => $requestItem['entity']->getEntityType(),
                            'outlookUserId' => $outlookUserId,
                            'contactId' => $responseData['id'],
                            'userId' => $userId,
                        ]);
                    }
                }

                continue;
            }

            if ($requestItem['type'] === 'PATCH') {
                if ($item['status'] === 404) {
                    $this->entityManager->removeEntity($requestItem['relationEntity']);

                    //$reCreateList[] = $requestItem['entity'];

                    $leftIdList[] = $requestItem['entity']->get('id');

                    continue;
                }

                if ($item['status'] === 200) {
                    $count++;
                }
            }
        }

        /*if (count($reCreateList)) {
             $count += $this->pushContacts($reCreateList, $userId, $externalAccount);
        }*/

        return (object) [
            'count' => $count,
            'leftIdList' => $leftIdList,
        ];
    }
}
