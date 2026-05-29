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
 * License ID: 11af5a568c1a72dce4e164257d1a0207
 ************************************************************************************/

namespace Espo\Modules\Sales\Tools\PaymentRequest\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Record\EntityProvider;
use Espo\Core\Record\ServiceContainer;
use Espo\Modules\Sales\Entities\PaymentRequest;
use Espo\Modules\Sales\Tools\PaymentRequest\Service;
use Espo\ORM\EntityManager;

/**
 * @noinspection PhpUnused
 */
class PostGenerateNewReferenceId implements Action
{
    public function __construct(
        private EntityProvider $entityProvider,
        private Service $service,
        private EntityManager $entityManager,
        private Acl $acl,
        private ServiceContainer $serviceContainer,
    ) {}

    public function process(Request $request): Response
    {
        $entity = $this->getEntity($request);

        $entity->setReferenceId($this->service->generateReferenceId());

        $this->entityManager->saveEntity($entity);

        $recordService = $this->serviceContainer->getByClass(PaymentRequest::class);

        $recordService->loadAdditionalFields($entity);
        $recordService->prepareEntityForOutput($entity);

        return ResponseComposer::json($entity->getValueMap());
    }

    /**
     * @throws BadRequest
     * @throws Forbidden
     * @throws NotFound
     */
    private function getEntity(Request $request): PaymentRequest
    {
        $id = $request->getRouteParam('id') ?? throw new BadRequest();

        $entity = $this->entityProvider->getByClass(PaymentRequest::class, $id);

        if (!$this->acl->checkEntityEdit($entity)) {
            throw new Forbidden("No 'edit' access.");
        }

        if ($entity->isNotActual()) {
            throw new Forbidden("Cannot generate new ID for non-open request.");
        }

        if ($entity->getStatus() === PaymentRequest::STATUS_IN_PROGRESS) {
            throw new Forbidden("Cannot generate new ID for in-progress request.");
        }

        return $entity;
    }
}
