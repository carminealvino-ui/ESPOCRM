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

namespace Espo\Modules\Sales\Tools\PaymentRequest;

use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Metadata;
use Espo\Modules\Sales\Entities\PaymentRequest;
use Espo\Modules\Sales\Tools\PaymentRequest\Checkout\Coordinator;
use Espo\Modules\Sales\Tools\PaymentRequest\Checkout\ProceedResult;
use RuntimeException;

class CheckoutService
{
    public function __construct(
        private Metadata $metadata,
        private InjectableFactory $injectableFactory,
    ) {}

    /**
     * @throws Forbidden
     * @throws Error
     */
    public function process(PaymentRequest $request): ProceedResult
    {
        if ($request->isNotActual()) {
            throw new Forbidden("Request is not open.");
        }

        $coordinator = $this->getCoordinator($request);

        return $coordinator->proceed($request);
    }

    private function getProvider(PaymentRequest $request): string
    {
        $provider = $request->getMethod()
            ->getChannel()
            ?->getProvider();

        if (!$provider) {
            throw new RuntimeException("No provider.");
        }

        return $provider;
    }

    private function getCoordinator(PaymentRequest $request): Coordinator
    {
        $provider = $this->getProvider($request);

        /** @var ?class-string $className */
        $className = $this->metadata
            ->get("app.salesPaymentProviders.$provider.paymentLink.checkoutCoordinatorClassName");

        if (!$className) {
            throw new RuntimeException("No checkout coordinator.");
        }

        $coordinator = $this->injectableFactory->create($className);

        if (!$coordinator instanceof Coordinator) {
            throw new RuntimeException("Bad coordinator instance");
        }

        return $coordinator;
    }
}
