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

use Espo\Core\Exceptions\Error;
use Espo\Core\ExternalAccount\ClientManager;
use Espo\Core\Mail\Smtp\Handler;
use Espo\Core\Mail\SmtpParams;
use Espo\Modules\Outlook\Core\Outlook\Clients\Outlook;
use Espo\ORM\EntityManager;
use RuntimeException;

class SmtpGroupHandler implements Handler
{
    protected $entityType = 'InboundEmail';

    public function __construct(
        private EntityManager $entityManager,
        private ClientManager $clientManager,
    ) {}

    /**
     * @throws Error
     */
    public function handle(SmtpParams $params, ?string $id): SmtpParams
    {
        $account = $this->entityManager->getRDBRepository($this->entityType)->getById($id);

        if (!$account) {
            throw new RuntimeException("SmtpHandler: $this->entityType $id not found.");
        }

        $username = $account->get('smtpUsername');

        if (!$username) {
            throw new RuntimeException("SmtpHandler: No 'smtpUsername'.");
        }

        /** @noinspection PhpUnhandledExceptionInspection */
        $client = $this->clientManager->create('Outlook', $id);

        if (!$client) {
            return $params;
        }

        if (!$client instanceof Outlook) {
            throw new RuntimeException("Wrong client object.");
        }

        if (!$client->getParam('expiresAt')) {
            // For bc.
            $client->getMailClient()->productPing();
            $accessToken = $client->getMailClient()->getParam('accessToken');
        } else {
            $client->handleAccessTokenActuality();

            $accessToken = $client->getParam('accessToken');
        }

        if (!$accessToken) {
            return $params;
        }

        if (
            method_exists($params, 'withAuthClassName') &&
            !method_exists($params, 'withTransportPreparatorClassName') // Skipping if greater than v9.1.
        ) {
            // For bc. Before v9.1.

            $authString = base64_encode("user=$username\1auth=Bearer $accessToken\1\1");

            $params = $params
                ->withAuthClassName('Espo\\Modules\\Outlook\\Core\\Outlook\\Smtp\\Auth\\Xoauth')
                ->withConnectionOptions(['authString' => $authString]);
        }

        if (
            method_exists($params, 'withTransportPreparatorClassName') &&
            $this->useGraphApi()
        ) {
            return $params
                ->withTransportPreparatorClassName("Espo\\Modules\\Outlook\\Core\\Outlook\\Smtp\\GraphApiTransportPreparator")
                ->withConnectionOptions(['client' => $client]);
        }

        return $params
            ->withUsername($username)
            ->withPassword($accessToken)
            ->withAuthMechanism('xoauth');
    }

    private function useGraphApi(): bool
    {
        $integration = $this->entityManager->getEntityById('Integration', 'Outlook');

        return (bool) $integration?->get('graphApiSendEmail');
    }
}
