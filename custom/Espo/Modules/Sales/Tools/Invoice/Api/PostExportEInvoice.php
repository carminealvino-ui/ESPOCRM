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

namespace Espo\Modules\Sales\Tools\Invoice\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Modules\Sales\Entities\CreditNote;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Tools\Invoice\EInvoice\Exceptions\RuleValidationFailure;
use Espo\Modules\Sales\Tools\Invoice\EInvoice\Exceptions\UnknownFormat;
use Espo\Modules\Sales\Tools\Invoice\EInvoice\Exceptions\UnsupportedTaxCombination;
use Espo\Modules\Sales\Tools\Invoice\EInvoice\UblGenerator;
use Espo\ORM\EntityManager;
use LogicException;

/**
 * @noinspection PhpUnused
 */
class PostExportEInvoice implements Action
{
    public function __construct(
        private Acl $acl,
        private EntityManager $entityManager,
        private UblGenerator $ublGenerator
    ) {}

    public function process(Request $request): Response
    {
        $id = $request->getRouteParam('id') ?? throw new BadRequest();
        $entityType = $request->getRouteParam('entityType') ?? throw new BadRequest();

        $format = $request->getParsedBody()->format ?? null;

        if (!$this->acl->checkScope($entityType)) {
            throw new Forbidden();
        }

        if (!is_string($format)) {
            throw new BadRequest("No format.");
        }

        $entity = $this->getEntity($entityType, $id);

        try {
            $attachment = $this->ublGenerator->generateAttachment($entity, $format);
        } catch (RuleValidationFailure $e) {
            throw Error::createWithBody(
                $e->getMessage(),
                Error\Body::create()
                    ->withMessageTranslation('ublRuleValidationFailure', Invoice::ENTITY_TYPE, [
                        'ruleId' => $e->getRuleId(),
                        'message' => $e->getMessage(),
                    ])
            );
        } catch (UnknownFormat) {
            throw new BadRequest("Unknown format.");
        } catch (UnsupportedTaxCombination $e) {
            throw new BadRequest("Unsupported tax combination.", previous: $e);
        }

        return ResponseComposer::json(['id' => $attachment->getId()]);
    }

    /**
     * @throws Forbidden
     * @throws NotFound
     */
    private function getEntity(string $entityType, string $id): Invoice|CreditNote
    {
        $entity = $this->entityManager->getRDBRepository($entityType)->getById($id);

        if (!$entity) {
            throw new NotFound();
        }

        if (!$entity instanceof Invoice && !$entity instanceof CreditNote) {
            throw new LogicException();
        }

        if (!$this->acl->checkEntityRead($entity)) {
            throw new Forbidden();
        }

        return $entity;
    }
}
