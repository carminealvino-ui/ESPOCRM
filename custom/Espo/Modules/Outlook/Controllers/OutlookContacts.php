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

namespace Espo\Modules\Outlook\Controllers;

use Espo\Core\Acl;
use Espo\Core\Api\Request;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\BadRequest;
use Espo\Modules\Outlook\Services\OutlookContacts as Service;

class OutlookContacts
{
    private Service $service;
    private Acl $acl;

    public function __construct(
        Service $service,
        Acl $acl
    ) {
        $this->service = $service;
        $this->acl = $acl;
    }

    public function postActionContactFolders()
    {
        return $this->service->contactFolders();
    }

    /**
     * @throws BadRequest
     * @throws Forbidden
     * @throws Error
     */
    public function postActionPush(Request $request): array
    {
        $data = $request->getParsedBody();

        if (!$this->acl->checkScope('OutlookContacts')) {
            throw new Forbidden();
        }

        if (empty($data->entityType)) {
            throw new BadRequest();
        }

        $entityType = $data->entityType;

        $params = [];

        if (isset($data->byWhere) && $data->byWhere) {
            $params['where'] = [];

            foreach ($data->where as $item) {
                $params['where'][] = (array) $item;
            }
        } else {
            if (empty($data->idList)) {
                throw new BadRequest();
            }

            $params['ids'] = $data->idList;
        }

        return [
            'count' => $this->service->push($entityType, $params)
        ];
    }
}
