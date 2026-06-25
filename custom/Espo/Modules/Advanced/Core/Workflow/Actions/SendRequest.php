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
 * License ID: 3fed3a66185f29dcda0e14c6ee94bf07
 ************************************************************************************/

namespace Espo\Modules\Advanced\Core\Workflow\Actions;

use Espo\Core\Acl\GlobalRestriction;
use Espo\Core\HttpClient;
use Espo\Core\ORM\Entity as CoreEntity;
use Espo\Core\Utils\Security\UrlCheck;
use Espo\Modules\Advanced\Tools\Workflow\Core\PlaceholderHelper;
use Espo\Core\Exceptions\Error;
use Espo\Modules\Advanced\Core\Workflow\Exceptions\SendRequestError;

use LogicException;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use stdClass;

use const CURLE_OPERATION_TIMEDOUT;
use const CURLE_OPERATION_TIMEOUTED;
use const CURLINFO_HEADER_SIZE;
use const CURLINFO_HTTP_CODE;
use const CURLOPT_CONNECTTIMEOUT;
use const CURLOPT_CUSTOMREQUEST;
use const CURLOPT_HEADER;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_REDIR_PROTOCOLS;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_TIMEOUT;
use const CURLOPT_URL;
use const CURLOPT_FOLLOWLOCATION;
use const JSON_UNESCAPED_UNICODE;

/**
 * @noinspection PhpUnused
 */
class SendRequest extends Base
{
    private ?PlaceholderHelper $placeholderHelper = null;
    private const MAX_REDIRECTS = 5;

    /**
     * @throws Error
     * @throws SendRequestError
     */
    protected function run(CoreEntity $entity, stdClass $actionData, array $options): bool
    {
        $method = $actionData->requestType ?? null;
        $contentType = $actionData->contentType ?? null;
        $url = $actionData->requestUrl ?? null;
        $content = $actionData->content ?? null;
        $contentVariable = $actionData->contentVariable ?? null;
        $inputHeaders = $actionData->headers ?? [];

        if (!$url) {
            throw new Error("Empty request URL.");
        }

        if (!$method) {
            throw new Error("Empty request method.");
        }

        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE', 'GET'])) {
            throw new Error("Not supported request method.");
        }

        $this->validateContentType($contentType);

        $payload = $this->getPayload(
            isJson: $this->isJson($method, $contentType, $inputHeaders),
            content: $content,
            contentVariable: $contentVariable,
        );

        $timeout = $this->config->get('workflowSendRequestTimeout', 7);

        $url = $this->prepareUrl($method, $url, $payload);
        $headers = $this->prepareHeaders($contentType, $inputHeaders);

        $this->logSendRequest($method, $payload);

        if (class_exists("Espo\\Core\\HttpClient\\ClientFactory")) {
            [$code, $body, $isTimeout] = $this->processWithClient(
                url: $url,
                timeout: $timeout,
                method: $method,
                payload: $payload,
                headers: $headers,
            );
        } else {
            [$code, $body, $isTimeout] = $this->processWithCurl(
                url: $url,
                timeout: $timeout,
                method: $method,
                payload: $payload,
                headers: $headers,
            );
        }

        $this->processFinal(
            code: $code,
            method: $method,
            url: $url,
            isTimeout: $isTimeout,
            body: $body,
        );

        return true;
    }

    /**
     * @throws Error
     */
    private function getPayload(
        bool $isJson,
        ?string $content,
        ?string $contentVariable,
    ): ?string {

        if (!$contentVariable) {
            if (!$content) {
                return null;
            }

            $content = $this->applyVariables($content, true);

            if ($isJson) {
                return $content;
            }

            $post = json_decode($content, true);

            foreach ($post as $k => $v) {
                if (is_array($v)) {
                    $post[$k] = '"' . implode(', ', $v) . '"';
                }
            }

            return http_build_query($post);
        }

        if ($contentVariable[0] === '$') {
            $contentVariable = substr($contentVariable, 1);

            if (!$contentVariable) {
                throw new Error("Empty variable.");
            }
        }

        $content = $this->getVariables()->$contentVariable ?? null;

        if (is_string($content)) {
            return $content;
        }

        if (!$content) {
            return null;
        }

        if (!$isJson) {
            if ($content instanceof stdClass) {
                return http_build_query($content);
            }

            throw new Error("Workflow: Send Request: Bad value in payload variable. Should be string or object.");
        }

        if (
            is_array($content) ||
            $content instanceof stdClass ||
            is_scalar($content)
        ) {
            $result = json_encode($content, JSON_UNESCAPED_UNICODE);

            if ($result === false) {
                throw new Error("Workflow: Send Request: Could not JSON encode payload.");
            }

            return $result;
        }

        throw new Error("Workflow: Send Request action: Bad value in payload variable.");
    }

    /**
     * @param string $body
     * @param int $code
     */
    private function setResponseVariables($body, $code): void
    {
        if (!$this->hasVariables()) {
            return;
        }

        $this->updateVariables(
            (object) [
                '_lastHttpResponseBody' => $body,
                '_lastHttpResponseCode' => $code,
            ]
        );
    }

    private function applyVariables(string $content, bool $isJson = false): string
    {
        $target = $this->getEntity();

        $restrictedAttributes = array_merge(
            $this->aclManager->getScopeRestrictedFieldList($target->getEntityType(), GlobalRestriction::TYPE_FORBIDDEN),
            $this->aclManager->getScopeRestrictedFieldList($target->getEntityType(), GlobalRestriction::TYPE_INTERNAL),
        );

        foreach ($target->getAttributeList() as $attribute) {
            if (in_array($attribute, $restrictedAttributes)) {
                continue;
            }

            $value = $target->get($attribute) ?? '';

            if (
                $isJson &&
                $target->getAttributeParam($attribute, 'isLinkMultipleIdList') &&
                $target->get($attribute) === null
            ) {
                $relation = $target->getAttributeParam($attribute, 'relation');

                if ($relation && $target->hasLinkMultipleField($relation)) {
                    $value = $target->getLinkMultipleIdList($relation);
                }
            }

            if (!$isJson && is_array($value)) {
                $arr = [];

                foreach ($value as $item) {
                    if (is_string($item)) {
                        $arr[] = str_replace(',', '_COMMA_', $item);
                    }
                }

                $value = implode(',', $arr);
            }

            if (is_string($value)) {
                $value = $isJson ?
                    $this->escapeStringForJson($value) :
                    str_replace(["\r\n", "\r", "\n"], "\\n", $value);
            } else if (is_numeric($value)) {
                $value = strval($value);
            } else if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            } else if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            if (is_string($value)) {
                $content = str_replace('{$' . $attribute . '}', $value, $content);
            }
        }

        $variables = $this->getVariables();

        foreach (get_object_vars($variables) as $key => $value) {
            if (
                !is_string($value) &&
                !is_int($value) &&
                !is_float($value) &&
                !is_array($value) &&
                !is_bool($value)
            ) {
                continue;
            }

            if (is_int($value) || is_float($value)) {
                $value = strval($value);
            } else if (is_array($value)) {
                if (!$isJson) {
                    continue;
                }

                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            } else if (is_string($value)) {
                $value = $isJson ?
                    $this->escapeStringForJson($value) :
                    str_replace(["\r\n", "\r", "\n"], "\\n", $value);
            } else if (is_bool($value)) {  /** @phpstan-ignore-line function.alreadyNarrowedType */
                $value = $value ? 'true' : 'false';
            } else {
                continue;
            }

            /** @var string $value */

            $content = str_replace("{\$\$$key}", $value, $content);
        }

        return $content;
    }

    private function escapeStringForJson(string $string): string
    {
        $encoded = json_encode($string, JSON_UNESCAPED_UNICODE);

        if ($encoded === false) {
            $encoded = '';
        }

        return substr($encoded, 1, -1);
    }

    private function getPlaceholderHelper(): PlaceholderHelper
    {
        $this->placeholderHelper ??= $this->injectableFactory->create(PlaceholderHelper::class);

        return $this->placeholderHelper;
    }

    private function logSendRequest(string $method, ?string $post): void
    {
        $logMessage = "Workflow: Send request.";

        if ($method !== 'GET') {
            $logMessage .= " Payload:" . $post;
        }

        $this->log->debug($logMessage);
    }

    private function getMaxRedirects(): int
    {
        return self::MAX_REDIRECTS;
    }

    private function getUrlCheck(): UrlCheck
    {
        return $this->injectableFactory->create(UrlCheck::class);
    }

    /**
     * @internal
     */
    private function isAllowedUrl(string $url): bool
    {
        $allowedAddressList = $this->getAllowedAddressList();

        if (!$allowedAddressList) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT);
        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (!is_string($host)) {
            return false;
        }

        if (!is_int($port)) {
            if ($scheme === 'https') {
                $port = 443;
            } else if ($scheme === 'http') {
                $port = 80;
            }
        }

        if (!is_int($port)) {
            return false;
        }

        $address = $host . ':' . $port;

        return in_array($address, $allowedAddressList);
    }

    private function getAllowRedirects(): bool
    {
        return true;
    }

    /**
     * @return non-empty-string
     * @throws Error
     */
    private function prepareUrl(string $method, string $url, ?string $payload): string
    {
        $isGet = $method === 'GET';

        /** @var non-empty-string $url */
        $url = $this->applyVariables($url);

        if ($isGet && $payload) {
            $separator = (parse_url($url, PHP_URL_QUERY) === null) ? '?' : '&';

            $url .= $separator . $payload;
        }

        $urlCheck = $this->getUrlCheck();

        if (
            !$this->isAllowedUrl($url) &&
            /** @phpstan-ignore-next-line function.alreadyNarrowedType */
            method_exists($urlCheck, 'isUrlAndNotIternal') &&
            !$urlCheck->isUrlAndNotIternal($url)
        ) {
            throw new Error("Forbidden URL '$url'.");
        }

        return $url;
    }

    /**
     * @throws Error
     * @throws SendRequestError
     */
    private function processFinal(
        int $code,
        string $method,
        string $url,
        bool $isTimeout,
        string $body,
    ): void {

        if ($code && $code >= 400 && $code <= 500) {
            $message = "Workflow: Send Request action: $method $url; Error $code response.";

            throw new SendRequestError($message, $code);
        }

        if ($isTimeout) {
            throw new Error("Workflow: Send Request action: $url; Timeout.");
        }

        if ($code < 200 || $code >= 300) {
            $message = "Workflow: Send Request action: $code response.";

            throw new SendRequestError($message, $code);
        }

        $this->setResponseVariables($body, $code);
    }

    /**
     * @param string[] $additionalHeaders
     */
    private function isJson(string $method, ?string $contentType, array $additionalHeaders): bool
    {
        $isGet = $method === 'GET';

        $isJson = $contentType === 'application/json' && !$isGet;

        if ($isJson) {
            return true;
        }

        foreach ($additionalHeaders as $header) {
            if (str_starts_with(strtolower($header), 'content-type: application/json')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string[] $inputHeaders
     * @return string[]
     */
    private function prepareHeaders(?string $contentType, array $inputHeaders): array
    {
        $headers = [];

        if ($contentType) {
            $headers[] = 'Content-Type: ' . $contentType;
        }

        foreach ($inputHeaders as $header) {
            $header = $this->applyVariables($header);
            $header = $this->getPlaceholderHelper()->applySecrets($header);

            $headers[] = $header;
        }

        return $headers;
    }

    /**
     * @throws Error
     */
    private function validateContentType(?string $contentType): void
    {
        $contentTypeList = [
            null,
            'application/json',
            'application/x-www-form-urlencoded',
        ];

        if (!in_array($contentType, $contentTypeList)) {
            throw new Error("Unsupported content-type.");
        }
    }

    /**
     * @param non-empty-string $url
     * @param non-empty-string $method
     * @param string[] $headers
     * @return array{int, string, bool}
     */
    private function processWithCurl(
        string $url,
        ?int $timeout,
        string $method,
        ?string $payload,
        array $headers,
    ): array {

        $ch = curl_init();

        if ($ch === false) {
            throw new RuntimeException("CURL init error.");
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS | CURLPROTO_HTTP);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $this->getAllowRedirects());
        curl_setopt($ch, CURLOPT_MAXREDIRS, $this->getMaxRedirects());

        if ($this->getAllowRedirects()) {
            curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS);
        }

        if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);

        if ($response === false || $response === true) {
            $response = '';
        }

        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_errno($ch);

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        $body = mb_substr($response, $headerSize);

        if (!is_int($code)) {
            $code = 0;
        }

        $isTimeout = $error && in_array($error, [CURLE_OPERATION_TIMEDOUT, CURLE_OPERATION_TIMEOUTED]);

        return [$code, $body, $isTimeout];
    }

    /**
     * @param non-empty-string $url
     * @param non-empty-string $method
     * @param string[] $headers
     * @return array{int, string, bool}
     * @throws Error
     */
    private function processWithClient(
        string $url,
        ?int $timeout,
        string $method,
        ?string $payload,
        array $headers,
    ): array {

        $factory = $this->injectableFactory->create(HttpClient\ClientFactory::class);

        $options = new HttpClient\Options(
            protocols: [HttpClient\Protocol::https, HttpClient\Protocol::http],
            redirect: new HttpClient\Options\Redirect(
                allow: true,
                protocols: [HttpClient\Protocol::https],
            ),
            timeout: $timeout,
            connectTimeout: $timeout,
            internalHostRestriction: new HttpClient\Options\InternalHostRestriction(
                restrict: true,
                allowed: $this->getAllowedAddressList(),
            ),
        );

        $request = $this->prepareRequest(
            method: $method,
            url: $url,
            headers: $headers,
            payload: $payload,
        );

        $client = $factory->create($options);

        try {
            $response = $client->send($request);
        } catch (HttpClient\Exceptions\ConnectException $e) {
            $isTimeout = $e->getReason() === HttpClient\ConnectErrorReason::Timeout;

            if ($isTimeout) {
                throw new Error("Workflow: Send Request action: $url; Timeout.", previous: $e);
            }

            return [0, '', false];
        } catch (HttpClient\Exceptions\TooManyRedirectsException $e) {
            throw new Error("Workflow: Send Request action: $url; Too many redirects.", previous: $e);
        }

        $code = $response->getStatusCode();
        $body = (string) $response->getBody();

        return [$code, $body, false];
    }

    /**
     * @return string[]
     */
    private function getAllowedAddressList(): array
    {
        /** @var string[] $allowedAddressList */
        $allowedAddressList = $this->config->get('workflowSendRequestAllowedAddressList') ?? [];

        return $allowedAddressList;
    }

    /**
     * @param non-empty-string $method
     * @param non-empty-string $url
     * @param string[] $headers
     */
    private function prepareRequest(string $method, string $url, array $headers, ?string $payload): RequestInterface
    {
        $request = HttpClient\RequestCreator::create($method, $url);

        foreach ($headers as $header) {
            if (!str_contains($header, ':')) {
                continue;
            }

            [$key, $value] = explode(':', $header, 2);

            $key = trim($key);
            $value = trim($value);

            if (!$key) {
                continue;
            }

            $request = $request->withHeader($key, $value);
        }

        if ($method !== 'GET' && $payload !== null) {
            $request = $request->withBody(HttpClient\Util::streamFor($payload));
        }

        if (!$request instanceof RequestInterface) {
            throw new LogicException();
        }

        return $request;
    }
}
