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
use Espo\Core\Utils\DateTime;
use Espo\Modules\Sales\Entities\PaymentRequest;
use Espo\ORM\EntityManager;
use Espo\ORM\Name\Attribute;

class ExpirationService
{
    public function __construct(
        private EntityManager $entityManager,
        private DateTime $dateTimeUtil,
    ) {}

    public function control(): void
    {
        $today = Date::createToday($this->dateTimeUtil->getTimezone());

        $requests = $this->entityManager
            ->getRDBRepositoryByClass(PaymentRequest::class)
            ->sth()
            ->where([
                'expirationDate<' => $today->toString(),
                'status' => [
                    PaymentRequest::STATUS_PENDING,
                ],
            ])
            ->find();

        foreach ($requests as $request) {
            $this->expireRequest($request);
        }
    }

    private function expireRequest(PaymentRequest $request): void
    {
        $this->entityManager->getTransactionManager()->start();

        $this->entityManager
            ->getRDBRepositoryByClass(PaymentRequest::class)
            ->select(Attribute::ID)
            ->forUpdate()
            ->where([Attribute::ID => $request->getId()])
            ->findOne();

        $request->setStatus(PaymentRequest::STATUS_EXPIRED);

        $this->entityManager->saveEntity($request);

        $this->entityManager->getTransactionManager()->commit();
    }
}
