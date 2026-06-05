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

namespace Espo\Modules\Sales\Tools\Subscription\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Utils\Metadata;
use Espo\Entities\User;
use Espo\Modules\Sales\Tools\Subscription\IntervalUnit;

/**
 * @noinspection PhpUnused
 */
class PutSubscriptionSettings implements Action
{
    public function __construct(
        private User $user,
        private Metadata $metadata,
    ) {}

    public function process(Request $request): Response
    {
        if (!$this->user->isAdmin()) {
            throw new Forbidden();
        }

        $intervals = $this->getIntervals($request);

        $this->metadata->set('app', 'salesSubscription', [
            'intervalList' => $intervals,
        ]);

        $this->metadata->save();

        return ResponseComposer::json((object) []);
    }

    /**
     * @return string[]
     * @throws BadRequest
     */
    private function getIntervals(Request $request): array
    {
        $intervals = $request->getParsedBody()->intervals ?? null;

        if (!is_array($intervals)) {
            throw new BadRequest("No intervals.");
        }

        if ($intervals === []) {
            throw new BadRequest("No intervals");
        }

        foreach ($intervals as $interval) {
            if (!is_string($interval)) {
                throw new BadRequest("Bad interval.");
            }

            $unit = $interval[strlen($interval) - 1] ?? null;

            if (!$unit) {
                throw new BadRequest();
            }

            if (!IntervalUnit::tryFrom($unit)) {
                throw new BadRequest();
            }

            $valueString = substr($interval, 0, -1);

            $value = (int) $valueString;

            if (strval($value) !== $valueString) {
                throw new BadRequest();
            }
        }

        return $intervals;
    }
}
