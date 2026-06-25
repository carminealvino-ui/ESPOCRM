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

use Espo\Core\Api\Request;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Utils\Config;
use Espo\Modules\Outlook\Services\OutlookMail as Service;
use Espo\ORM\EntityManager;

class OutlookMail
{
    private Service $service;
    private EntityManager $entityManager;
    private Config $config;

    public function __construct(
        Service $service,
        EntityManager $entityManager,
        Config $config
    ) {
        $this->service = $service;
        $this->entityManager = $entityManager;
        $this->config = $config;
    }

    /**
     * @throws BadRequest
     * @throws Forbidden
     * @throws NotFound
     * @throws Error
     */
    public function postActionConnect(Request $request)
    {
        $data = $request->getParsedBody();

        $entityType = $data->entityType ?? null;
        $id = $data->id ?? null;
        $code = $data->code ?? null;

        if (!$entityType) {
            throw new BadRequest();
        }

        if (!$id) {
            throw new BadRequest();
        }

        if (!$code) {
            throw new BadRequest();
        }

        $this->service->processAccessCheck($entityType, $id);

        return $this->service->connect($entityType, $id, $code);
    }

    /**
     * @throws BadRequest
     * @throws Forbidden
     */
    public function postActionDisconnect(Request $request): bool
    {
        $data = $request->getParsedBody();

        $entityType = $data->entityType ?? null;
        $id = $data->id ?? null;

        if (!$entityType) {
            throw new BadRequest();
        }

        if (!$id) {
            throw new BadRequest();
        }

        $this->service->processAccessCheck($entityType, $id);

        $this->service->disconnect($entityType, $id);

        return true;
    }

    /**
     * @throws BadRequest
     * @throws NotFound
     * @throws Forbidden
     */
    public function postActionPing(Request $request): array
    {
        $data = $request->getParsedBody();

        $entityType = $data->entityType ?? null;
        $id = $data->id ?? null;

        if (!$entityType) {
            throw new BadRequest();
        }

        if (!$id) {
            throw new BadRequest();
        }

        $this->service->processAccessCheck($entityType, $id);

        $integration = $this->entityManager->getEntityById('Integration', 'Outlook');

        if (!$integration) {
            throw new NotFound();
        }

        return [
            'clientId' => $integration->get('clientId'),
            'redirectUri' => $this->config->get('siteUrl') . '/oauth-callback.php',
            'isConnected' => $this->service->ping($entityType, $id),
        ];
    }
}
