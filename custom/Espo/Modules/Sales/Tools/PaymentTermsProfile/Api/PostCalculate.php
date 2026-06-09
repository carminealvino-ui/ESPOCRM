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

namespace Espo\Modules\Sales\Tools\PaymentTermsProfile\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Field\Date;
use Espo\Core\Record\EntityProvider;
use Espo\Modules\Sales\Entities\PaymentTermsProfile;
use Espo\Modules\Sales\Tools\PaymentTermsProfile\TermsCalculator;
use RuntimeException;
use Throwable;

/**
 * @noinspection PhpUnused
 */
class PostCalculate implements Action
{
    public function __construct(
        private TermsCalculator $termsCalculator,
        private EntityProvider $entityProvider,
    ) {}

    public function process(Request $request): Response
    {
        $dateIssuedString = $request->getParsedBody()->dateIssued ?? null;

        if (!is_string($dateIssuedString)) {
            throw new BadRequest("No dateIssued.");
        }

        try {
            $dateIssued = Date::fromString($dateIssuedString);
        } catch (Throwable) {
            throw new BadRequest("Bad dateIssued.");
        }

        $profile = $this->getProfile($request);

        $dateDue = $this->termsCalculator->calculateDateDue($profile, $dateIssued);

        return ResponseComposer::json([
            'dateDue' => $dateDue->toString(),
        ]);
    }

    /**
     * @throws Forbidden
     * @throws NotFound
     */
    private function getProfile(Request $request): PaymentTermsProfile
    {
        $id = $request->getRouteParam('id') ?? throw new RuntimeException();

        return $this->entityProvider->getByClass(PaymentTermsProfile::class, $id);
    }
}
