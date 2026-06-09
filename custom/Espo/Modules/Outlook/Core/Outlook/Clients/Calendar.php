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

use DateTime;
use Espo\Core\Exceptions\Error;
use Espo\Core\ExternalAccount\OAuth2\Client;
use Espo\Modules\Outlook\Core\Outlook\Exceptions\ApiError;

class Calendar extends Outlook
{
    protected function getPingUrl()
    {
        return $this->buildUrl('calendars') . '?$select=id,name';
    }

    /**
     * @throws Error
     * @throws ApiError
     */
    public function getCalendarList(array $params = [])
    {
        $method = 'GET';

        $url = $this->buildUrl('calendars') . '?$select=id,name';

        return $this->request($url, $params, $method);
    }

    /**
     * @param array{
     *     start?: ?string,
     *     end?: ?string,
     *     url?: ?string,
     *     deltaToken?: ?string,
     *     skipToken?: ?string,
     *     maxPageSize?: int,
     * } $params
     * @return array<string, mixed>
     * @throws ApiError
     * @throws Error
     */
    public function requestSync(string $calendarId, array $params = [])
    {
        $requestParams = [];

        $url = $this->baseUrl . "calendars('".$calendarId."')/calendarView/delta";

        $isFirstRun = true;
        $isSyncFinished = false;

        if (isset($params['url'])) {
            $url = $params['url'];
        } else {
            if (isset($params['start'])) {
                $dt = new DateTime($params['start']);

                $requestParams['startDateTime'] = $dt->format('c');
            }
            if (isset($params['end'])) {
                $dt = new DateTime($params['end']);

                $requestParams['endDateTime'] = $dt->format('c');
            }
            if (isset($params['deltaToken'])) {
                $requestParams['$deltaToken'] = $params['deltaToken'];
                $isFirstRun = false;
            }
            if (isset($params['skipToken'])) {
                $requestParams['$skipToken'] = $params['skipToken'];
                $isFirstRun = false;
            }
        }

        $headers = [
            'Prefer: odata.track-changes',
        ];

        if (isset($params['maxPageSize'])) {
            $headers[] = 'Prefer: odata.maxpagesize=' . $params['maxPageSize'];
        }

        $result = $this->request($url, $requestParams, Client::HTTP_METHOD_GET, null, true, $headers);

        $resultData = [];

        if (isset($result['@odata.deltaLink'])) {
            $deltaLink = $result['@odata.deltaLink'];

            $deltaLink = urldecode($deltaLink);

            $parts = parse_url($deltaLink);

            parse_str($parts['query'], $query);

            $deltaToken = $query['$deltatoken'] ?? $query['$deltaToken'] ?? null;

            $resultData['deltaToken'] = $deltaToken;

            if (!$isFirstRun) {
                $isSyncFinished = true;
            }

        } else if (isset($result['@odata.nextLink'])) {
            $nextLink = $result['@odata.nextLink'];
            $nextLink = urldecode($nextLink);
            $parts = parse_url($nextLink);

            parse_str($parts['query'], $query);
            $skipToken = $query['$skipToken'] ?? ($query['$skiptoken'] ?? null);

            $resultData['skipToken'] = $skipToken;
        }

        if (isset($result['value']) && is_array($result['value'])) {
            $resultData['itemList'] = $result['value'];
        }

        $resultData['isSyncFinished'] = $isSyncFinished;

        return $resultData;
    }
}
