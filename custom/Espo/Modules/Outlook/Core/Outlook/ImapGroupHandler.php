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
use Espo\Core\Mail\Account\Storage\Params;
use Espo\Core\Mail\Exceptions\ImapError;
use Espo\Modules\Outlook\Core\Outlook\Clients\Outlook;
use Espo\ORM\EntityManager;
use Laminas\Mail\Protocol\Exception\ExceptionInterface;
use Laminas\Mail\Protocol\Imap;
use RuntimeException;

class ImapGroupHandler
{
    protected $entityType = 'InboundEmail';

    private EntityManager $entityManager;
    private ClientManager $clientManager;

    public function __construct(
        EntityManager $entityManager,
        ClientManager $clientManager
    ) {
        $this->entityManager = $entityManager;
        $this->clientManager = $clientManager;
    }

    public function handle(Params $params, string $id): Params
    {
        $accessToken = $this->getAccessToken($id);

        if (!$accessToken) {
            throw new ImapError("Could not get access token.");
        }

        return $params->withAuthMechanism(Params::AUTH_MECHANISM_XOAUTH)
            ->withPassword($accessToken);
    }

    /**
     * For bc.
     *
     * @throws Error
     * @throws ExceptionInterface
     */
    public function prepareProtocol(string $id, array $params)
    {
        $inboundEmail = $this->entityManager->getRepository($this->entityType)->getById($id);

        if (!$inboundEmail) {
            throw new RuntimeException("ImapHandler: $this->entityType $id not found.");
        }

        $username = $inboundEmail->get('username');

        if (!$username) {
            throw new RuntimeException("ImapHandler: No username.");
        }

        $accessToken = $this->getAccessToken($id);

        if (!$accessToken) {
            return null;
        }

        $ssl = false;

        if (!empty($params['ssl'])) {
            $ssl = 'SSL';
        }

        if (!empty($params['security'])) {
            $ssl = $params['security'];
        }

        $protocol = new Imap($params['host'], $params['port'], $ssl);

        $authString = base64_encode("user=$username\1auth=Bearer $accessToken\1\1");

        $authenticateParams = ['XOAUTH2', $authString];
        $protocol->sendRequest('AUTHENTICATE', $authenticateParams);

        $i = 0;

        while (true) {
            if ($i === 10) {
                return null;
            }

            $response = '';
            $isPlus = $protocol->readLine($response, '+', true);

            if ($isPlus) {
                $GLOBALS['log']->error("Outlook Imap: Extra server challenge: " . $response);
                $protocol->sendRequest('');
            }
            else {
                if (
                    preg_match('/^NO /i', $response) ||
                    preg_match('/^BAD /i', $response)
                ) {
                    $GLOBALS['log']->error("Outlook Imap: Failure: " . $response);

                    return null;
                }
                else if (preg_match("/^OK /i", $response)) {
                    break;
                }
            }

            $i++;
        }

        return $protocol;
    }

    /**
     * @throws Error
     */
    private function getAccessToken(string $id): ?string
    {
        /** @var Outlook $client */
        $client = $this->clientManager->create('Outlook', $id);

        if (!$client) {
            return null;
        }

        if (!$client->getParam('expiresAt')) {
            // for backward compatibility
            $client->getMailClient()->productPing();
            $accessToken = $client->getMailClient()->getParam('accessToken');
        } else {
            $client->handleAccessTokenActuality();
            $accessToken = $client->getParam('accessToken');
        }

        return $accessToken;
    }
}
