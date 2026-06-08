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
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\ExternalAccount\ClientManager;
use Espo\Core\InjectableFactory;
use Espo\Entities\User;
use Espo\Modules\Outlook\Core\Outlook\Clients\Outlook;
use Espo\ORM\EntityManager;
use Espo\Services\ExternalAccount;
use Exception;

class OutlookMail
{
    private User $user;
    private EntityManager $entityManager;
    private Acl $acl;
    private InjectableFactory $injectableFactory;
    private ClientManager $clientManager;

    public function __construct(
        User $user,
        EntityManager $entityManager,
        Acl $acl,
        InjectableFactory $injectableFactory,
        ClientManager $clientManager
    ) {
        $this->user = $user;
        $this->entityManager = $entityManager;
        $this->acl = $acl;
        $this->injectableFactory = $injectableFactory;
        $this->clientManager = $clientManager;
    }


    /**
     * @throws Forbidden
     */
    public function processAccessCheck(string $entityType, string $id): void
    {
        if ($this->user->isAdmin()) {
            return;
        }

        if ($entityType === 'EmailAccount') {
            $record = $this->entityManager->getEntityById('EmailAccount', $id);

            if (!$record) {
                throw new Forbidden();
            }

            if (!$this->acl->check($record)) {
                throw new Forbidden();
            }

            return;
        }

        throw new Forbidden();
    }

    /**
     * @throws Error
     * @throws NotFound
     */
    public function connect(string $entityType, string $id, string $code) : bool
    {
        $em = $this->entityManager;

        $this->injectableFactory
            ->create(ExternalAccount::class)
            ->authorizationCode('Outlook', $id, $code);

        if ($entityType === 'EmailAccount') {
            $imapHandler = 'Espo\\Modules\\Outlook\\Core\\Outlook\\ImapPersonalHandler';
            $smtpHandler = 'Espo\\Modules\\Outlook\\Core\\Outlook\\SmtpPersonalHandler';
        }
        else {
            $imapHandler = 'Espo\\Modules\\Outlook\\Core\\Outlook\\ImapGroupHandler';
            $smtpHandler = 'Espo\\Modules\\Outlook\\Core\\Outlook\\SmtpGroupHandler';
        }

        $inboundEmail = $em->getRepository($entityType)->getById($id);

        if ($inboundEmail) {
            $inboundEmail->set('imapHandler', $imapHandler);
            $inboundEmail->set('smtpHandler', $smtpHandler);

            $em->saveEntity($inboundEmail);
        }

        return true;
    }

    public function disconnect(string $entityType, string $id)
    {
        $em = $this->entityManager;

        $ea = $em->getRepository('ExternalAccount')->getById('Outlook__' . $id);

        if ($ea) {
            $ea->set([
                'accessToken' => null,
                'refreshToken' => null,
                'tokenType' => null,
                'enabled' => false,
            ]);

            $em->saveEntity($ea, ['silent' => true]);
        }

        $inboundEmail = $em->getRepository($entityType)->getById($id);

        if ($inboundEmail) {
            $inboundEmail->set('imapHandler', null);
            $inboundEmail->set('smtpHandler', null);

            $em->saveEntity($inboundEmail);
        }

        return true;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function ping(string $entityType, string $id)
    {
        $integration = $this->entityManager->getEntityById('ExternalAccount', 'Outlook__' . $id);

        if (!$integration) {
            return false;
        }

        try {
            /** @var Outlook $client */
            $client = $this->clientManager->create('Outlook', $id);

            if ($client) {
                return true;
                //return $client->getMailClient()->productPing();
            }
        } catch (Exception $e) {}

        return false;
    }
}
