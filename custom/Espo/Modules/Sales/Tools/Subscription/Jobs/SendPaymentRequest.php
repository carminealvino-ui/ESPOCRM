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

namespace Espo\Modules\Sales\Tools\Subscription\Jobs;

use Espo\Core\Job\Job;
use Espo\Core\Job\Job\Data;
use Espo\Core\Mail\EmailSender;
use Espo\Core\Mail\Exceptions\SendingError;
use Espo\Entities\Email;
use Espo\Modules\Sales\Entities\Subscription;
use Espo\Modules\Sales\Entities\PaymentRequest;
use Espo\Modules\Sales\Entities\SubscriptionPeriod;
use Espo\Modules\Sales\Tools\PaymentRequest\PrepareEmailAttributesService;
use Espo\ORM\EntityManager;
use RuntimeException;

class SendPaymentRequest implements Job
{
    public const PARAM_PERIOD_ID = 'periodId';
    public const PARAM_SUBSCRIPTION_ID = 'subscriptionId';

    public function __construct(
        private EntityManager $entityManager,
        private PrepareEmailAttributesService $prepareEmailAttributesService,
        private EmailSender $emailSender,
    ) {}

    public function run(Data $data): void
    {
        $request = $this->getRequest($data);
        $period = $this->getPeriod($data);
        $subscription = $period?->getSubscription() ?? $this->getSubscription($data);

        $email = $this->entityManager->getRDBRepositoryByClass(Email::class)->getNew();

        $this->applyEmailAttributes(
            request: $request,
            period: $period,
            subscription: $subscription,
            email: $email,
        );

        $this->entityManager->saveEntity($email);

        // @todo Apply email account SMTP.

        try {
            $this->emailSender->send($email);
        } catch (SendingError $e) {
            throw new RuntimeException("Could not send payment request {$request->getId()}.", 0, $e);
        }
    }

    private function getRequest(Data $data): PaymentRequest
    {
        $id = $data->getTargetId();

        if (!$id) {
            throw new RuntimeException("No ID.");
        }

        $request = $this->entityManager->getRDBRepositoryByClass(PaymentRequest::class)->getById($id);

        if (!$request) {
            throw new RuntimeException("Payment request $id not found.");
        }

        return $request;
    }

    private function getPeriod(Data $data): ?SubscriptionPeriod
    {
        $id = $data->get(self::PARAM_PERIOD_ID);

        if (!$id) {
            return null;
        }

        $period = $this->entityManager->getRDBRepositoryByClass(SubscriptionPeriod::class)->getById($id);

        if (!$period) {
            throw new RuntimeException("Subscription period $id not found.");
        }

        return $period;
    }

    private function getSubscription(Data $data): Subscription
    {
        $id = $data->get(self::PARAM_SUBSCRIPTION_ID);

        if (!$id) {
            throw new RuntimeException("No subscription ID.");
        }

        $subscription = $this->entityManager->getRDBRepositoryByClass(Subscription::class)->getById($id);

        if (!$subscription) {
            throw new RuntimeException("Subscription $id not found.");
        }

        return $subscription;
    }

    private function applyEmailAttributes(
        PaymentRequest $request,
        ?SubscriptionPeriod $period,
        Subscription $subscription,
        Email $email,
    ): void {

        // @todo Introduce an additional email template parameter? Fallback to the default.

        $entityList = [$subscription];

        if ($period) {
            $entityList[] = $period;
        }

        $attributes = $this->prepareEmailAttributesService->prepare($request, $entityList);

        $email->setMultiple($attributes);
    }
}
