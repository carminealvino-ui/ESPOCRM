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

namespace Espo\Modules\Outlook\Core\Outlook\Clients;

use Espo\Core\Exceptions\Error;
use Espo\Core\ExternalAccount\ClientManager;
use Espo\Core\ExternalAccount\Clients\OAuth2Abstract;
use Espo\Core\ExternalAccount\OAuth2\Client;
use Espo\Core\Utils\Json;
use Espo\Core\Utils\Log;
use Espo\Core\Utils\Metadata;
use Espo\Modules\Outlook\Core\Outlook\Exceptions\ApiError;
use InvalidArgumentException;
use const JSON_PRETTY_PRINT;

class Outlook extends OAuth2Abstract
{
    protected $baseUrl = 'https://graph.microsoft.com/v1.0/me/';

    protected $calendar;
    protected $contacts;
    protected $mail;
    protected $redirectUri = null;
    protected $original = null;
    protected $tenant = 'common';

    /** @noinspection RegExpRedundantEscape */
    const HEADER_REGEX =
        "(^([^()<>@,;:\\\"/[\]?={}\x01-\x20\x7F]++):[ \t]*+((?:[ \t]*+[\x21-\x7E\x80-\xFF]++)*+)[ \t]*+\r?\n)m";
    const HEADER_FOLD_REGEX = "(\r?\n[ \t]++)";
    const ACCESS_TOKEN_EXPIRATION_MARGIN = '20 seconds';

    /**
     * @noinspection PhpMissingParentConstructorInspection
     */
    public function __construct(
        Client $client,
        array $params,
        ClientManager $manager,
        ?Log $log = null,
        private ?Metadata $metadata = null,
    ) {
        $this->client = $client;
        $this->manager = $manager;
        $this->log = $log ?? $GLOBALS['log'];

        $this->setParams($params);

        $this->baseUrl = $this->metadata?->get("integrations.Outlook.params.apiBaseUrl") ?? $this->baseUrl;
    }

    public function getParam($name)
    {
        $method = '_getParam' . ucfirst($name);

        if (method_exists($this, $method)) {
            return $this->$method();
        }

        return parent::getParam($name);
    }

    public function setParams(array $params)
    {
        foreach ($params as $k => $v) {
            $method = '_setParam' . ucfirst($k);

            if (method_exists($this, $method)) {
                $this->$method($v);
            }
        }

        parent::setParams($params);
    }

    public function setParam($name, $value)
    {
        $method = '_setParam' . ucfirst($name);

        if (method_exists($this, $method)) {
            $this->$method($value);

            return;
        }

        parent::setParam($name, $value);
    }

    /** @noinspection PhpUnused */
    protected function _getParamTokenEndpoint()
    {
        $endpoint = $this->tokenEndpoint;

        $tenant = $this->tenant ?: 'common';

        return str_replace('{tenant}', $tenant, $endpoint);
    }

    /** @noinspection PhpUnused */
    protected function _setParamTenant($value)
    {
        $this->tenant = $value;
    }

    /**
     * @param string $url
     * @return string
     */
    public function buildUrl($url)
    {
        return $this->baseUrl . trim($url, '\/');
    }

    /**
     * @return mixed
     * @throws Error
     */
    public function requestUserData()
    {
        return $this->request($this->baseUrl);
    }

    public function setOriginal($original)
    {
        $this->original = $original;
    }

    /**
     * @throws Error
     */
    public function batchRequest(array $itemList)
    {
        $httpHeaders = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        $requestData = (object) [
            'requests' => $itemList,
        ];

        $body = json_encode($requestData, JSON_PRETTY_PRINT);

        $url = $this->buildUrl('../$batch');

        $responseHeaders = [];

        $response = $this->request($url, $body, Client::HTTP_METHOD_POST, null, true, $httpHeaders, $responseHeaders);

        return $response['responses'] ?? [];
    }

    /**
     * @param string $url
     * @param array<string, mixed>|string|null $params
     * @param string $httpMethod
     * @param ?string $contentType
     * @param bool $allowRenew
     * @return mixed
     * @throws Error
     * @throws ApiError
     */
    public function request(
        $url,
        $params = null,
        $httpMethod = Client::HTTP_METHOD_GET,
        $contentType = null,
        $allowRenew = true,
        ?array $httpHeaders = null,
        ?array &$responseHeaders = null
    ) {
        if ($this->original) {
            return $this->original->request(
                $url,
                $params,
                $httpMethod,
                $contentType,
                $allowRenew,
                $httpHeaders,
                $responseHeaders
            );
        }

        $this->handleAccessTokenActuality();

        $httpHeaders = $httpHeaders ?? [];

        if (!empty($contentType)) {
            $httpHeaders['Content-Type'] = $contentType;

            switch ($contentType) {
                case Client::CONTENT_TYPE_APPLICATION_JSON:
                case Client::CONTENT_TYPE_MULTIPART_FORM_DATA:
                    $httpHeaders['Content-Length'] = strlen($params);

                    break;

            }
        }

        $response = $this->client->request($url, $params, $httpMethod, $httpHeaders);

        $code = null;

        if (!empty($response['code'])) {
            $code = intval($response['code']);
        }

        if (!is_null($responseHeaders)) {
            if (isset($response['header'])) {
                $msg = $response['header'] . "\n";
                $responseHeaders = $this->parseResponse($msg)['headers'];
            }
        }

        if ($code >= 200 && $code < 300) {
            return $response['result'];
        }

        $handledData = $this->handleErrorResponse($response);

        if ($allowRenew && is_array($handledData)) {
            if ($handledData['action'] == 'refreshToken') {
                $GLOBALS['log']->debug(
                    "Outlook: Refresh token action required for client $this->clientId; Response: " .
                    json_encode($response)
                );

                if ($this->refreshToken()) {
                    return $this->request($url, $params, $httpMethod, $contentType, false);
                }
            }
            else if ($handledData['action'] == 'renew') {
                $GLOBALS['log']->debug(
                    "Outlook: Renew action required for client $this->clientId; Response: " . json_encode($response));

                return $this->request($url, $params, $httpMethod, $contentType, false);
            }
        }

        $reasonPart = '';

        if (isset($response['result']['error']['message'])) {
            $reasonPart = '; Reason: ' . $response['result']['error']['message'];
        }

        $errorResult = $response['result']['error'] ?? [];

        throw ApiError::create(
            "Outlook Oauth: Error after requesting $httpMethod $url$reasonPart. Code: $code.",
            $errorResult,
            $code
        );
    }

    /**
     * @return string
     */
    protected function getPingUrl()
    {
        return '';
    }

    private function getParams()
    {
        $params = [];

        foreach ($this->paramList as $name) {
            $params[$name] = $this->$name;
        }

        return $params;
    }

    public function getCalendarClient(): Calendar
    {
        if (empty($this->calendar)) {
            $this->calendar = new Calendar(
                client: $this->client,
                params: $this->getParams(),
                manager: $this->manager,
                log: $this->log,
                metadata: $this->metadata,
            );

            $this->calendar->setOriginal($this);
        }
        return $this->calendar;
    }

    public function getContactsClient()
    {
        if (empty($this->contacts)) {
            $this->contacts = new Contacts(
                client: $this->client,
                params: $this->getParams(),
                manager: $this->manager,
                log: $this->log,
                metadata: $this->metadata,
            );

            $this->contacts->setOriginal($this);
        }

        return $this->contacts;
    }

    /**
     * @return Mail
     */
    public function getMailClient()
    {
        if (empty($this->mail)) {
            $this->mail = new Mail(
                client: $this->client,
                params: $this->getParams(),
                manager: $this->manager,
                log: $this->log,
                metadata: $this->metadata,
            );

            $this->mail->setOriginal($this);
        }

        return $this->mail;
    }

    public function ping()
    {
        if (empty($this->clientId)) {
            $GLOBALS['log']->notice("Outlook: Can't ping because empty clientId.");

            return false;
        }

        if (empty($this->accessToken)) {
            $GLOBALS['log']->notice("Outlook: Can't ping because empty accessToken for client $this->clientId.");

            return false;
        }

        if (empty($this->clientSecret)) {
            $GLOBALS['log']->notice("Outlook: Can't ping because empty clientSecret for client $this->clientId.");
            return false;
        }

        return $this->productPing($this->getCalendarClient()->getPingUrl());
    }

    public function productPing($url = null)
    {
        if (!$url) {
            $url = $this->getPingUrl();
        }

        try {
            $this->request($url);

            return true;
        }
        catch (\Exception $e) {
            $GLOBALS['log']->notice("Outlook: Ping failed for client $this->clientId: " . $e->getMessage());

            return false;
        }
    }

    /**
     * @param array<string, mixed> $response
     * @return ?array{action: string}
     */
    protected function handleErrorResponse($response)
    {
        if ($response['code'] == 401 && !empty($response['result'])) {
            $result = $response['result'];

            if (
                !empty($result['error']) && !empty($result['error']['code']) &&
                (in_array($result['error']['code'], ['InvalidMsaTicket', 'TokenExpired']))
            ) {
                return ['action' => 'refreshToken'];
            }

            return ['action' => 'renew'];
        }

        if ($response['code'] == 400 && !empty($response['result'])) {
            $result = $response['result'];

            if (
                !empty($result['error']) && !empty($result['error']['code']) &&
                (in_array($result['error']['code'], ['InvalidMsaTicket', 'TokenExpired']))
            ) {
                return ['action' => 'refreshToken'];
            }
        }

        return null;
    }

    protected function parseResponse($message)
    {
        if (!$message) {
            throw new InvalidArgumentException('Invalid message');
        }

        if (strpos($message, 'HTTP/1.1 100 Continue') === 0) {
            $message = substr($message, 21);
        }

        $message = ltrim($message, "\r\n");

        $messageParts = preg_split("/\r?\n\r?\n/", $message, 2);

        if ($messageParts === false || count($messageParts) !== 2) {
            throw new InvalidArgumentException('Invalid message: Missing header delimiter');
        }

        [$rawHeaders, $body] = $messageParts;
        $rawHeaders .= "\r\n"; // Put back the delimiter we split previously
        $headerParts = preg_split("/\r?\n/", $rawHeaders, 2);

        if ($headerParts === false || count($headerParts) !== 2) {
            throw new InvalidArgumentException('Invalid message: Missing status line');
        }

        [$startLine, $rawHeaders] = $headerParts;

        if (
            preg_match("/(?:^HTTP\/|^[A-Z]+ \S+ HTTP\/)(\d+(?:\.\d+)?)/i", $startLine, $matches) &&
            $matches[1] === '1.0'
        ) {
            // Header folding is deprecated for HTTP/1.1, but allowed in HTTP/1.0
            $rawHeaders = preg_replace(self::HEADER_FOLD_REGEX, ' ', $rawHeaders);
        }

        /** @var array[] $headerLines */
        $count = preg_match_all(self::HEADER_REGEX, $rawHeaders, $headerLines, PREG_SET_ORDER);

        // If these aren't the same, then one line didn't match and there's an invalid header.
        if ($count !== substr_count($rawHeaders, "\n")) {
            // Folding is deprecated, see https://tools.ietf.org/html/self#section-3.2.4
            if (preg_match(self::HEADER_FOLD_REGEX, $rawHeaders)) {
                throw new InvalidArgumentException('Invalid header syntax: Obsolete line folding');
            }

            throw new InvalidArgumentException('Invalid header syntax');
        }

        $headers = [];

        foreach ($headerLines as $headerLine) {
            $headers[$headerLine[1]][] = $headerLine[2];
        }

        return [
            'start-line' => $startLine,
            'headers' => $headers,
            'body' => $body,
        ];
    }


    /**
     * @param array<string, mixed> $data
     * @throws Error
     * @throws ApiError
     */
    public function sendEmail(array $data): void
    {
        $url = $this->buildUrl('sendMail');

        $this->request($url, Json::encode($data), 'POST', 'application/json');
    }
}
