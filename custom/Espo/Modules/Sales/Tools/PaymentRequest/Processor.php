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

use Espo\Core\Field\Date;
use Espo\Core\Job\Job\Data as JobData;
use Espo\Core\Job\JobSchedulerFactory;
use Espo\Core\Utils\Config\ApplicationConfig;
use Espo\Modules\Sales\Entities\PaymentEntry;
use Espo\Modules\Sales\Entities\PaymentMethod;
use Espo\Modules\Sales\Entities\PaymentRequest;
use Espo\Modules\Sales\Tools\Payment\Type;
use Espo\Modules\Sales\Tools\PaymentRequest\Jobs\ProcessPaymentPaid;
use Espo\ORM\EntityManager;

use Exception;
use RuntimeException;
use DateTimeZone;
use UnexpectedValueException;

class Processor
{
    public function __construct(
        private EntityManager $entityManager,
        private ApplicationConfig $applicationConfig,
        private JobSchedulerFactory $jobSchedulerFactory,
    ) {}

    public function processPaid(PaymentRequest $request, ?PaymentEntry $payment = null): void
    {
        $payment = $this->preparePayment($payment, $request);

        $request->setStatus(PaymentRequest::STATUS_PAID);

        $this->entityManager->saveEntity($payment);
        $this->entityManager->saveEntity($request);

        $this->jobSchedulerFactory
            ->create()
            ->setClassName(ProcessPaymentPaid::class)
            ->setData(JobData::create()->withTargetId($payment->getId()))
            ->schedule();

        // @todo Add log record.
    }

    public function processInProgress(PaymentRequest $request): void
    {
        $request->setStatus(PaymentRequest::STATUS_IN_PROGRESS);

        $this->entityManager->saveEntity($request);

        // @todo Add log record.
    }

    public function processSessionExpired(PaymentRequest $request): void
    {
        $request->setStatus(PaymentRequest::STATUS_PENDING);

        $this->entityManager->saveEntity($request);

        // @todo Add log record.
    }

    public function processFailed(PaymentRequest $request): void
    {
        $request->setStatus(PaymentRequest::STATUS_PENDING);

        $this->entityManager->saveEntity($request);

        // @todo Add log record.
    }

    private function preparePayment(?PaymentEntry $payment, PaymentRequest $request): PaymentEntry
    {
        $isNew = $payment === null;

        $payment ??= $this->entityManager->getRDBRepositoryByClass(PaymentEntry::class)->getNew();

        $payment->setStatus(PaymentEntry::STATUS_IN_PROGRESS);
        $payment->setRequest($request);
        $payment->setAccount($request->getAccount());
        $payment->setMethod($this->getMethod($request));
        $payment->setDatePaid($this->prepareToday());
        $payment->setType(Type::Inbound);
        $payment->setTeams($request->getTeams());

        if ($isNew || !$payment->getAmount()) {
            $payment->setAmount($request->getAmount());
        }

        return $payment;
    }

    private function prepareToday(): Date
    {
        try {
            return Date::createToday(new DateTimeZone($this->applicationConfig->getTimeZone()));
        } catch (Exception $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }
    }

    private function getMethod(PaymentRequest $request): ?PaymentMethod
    {
        try {
            $method = $request->getMethod();
        } catch (UnexpectedValueException) {
            $method = null;
        }

        return $method;
    }
}
