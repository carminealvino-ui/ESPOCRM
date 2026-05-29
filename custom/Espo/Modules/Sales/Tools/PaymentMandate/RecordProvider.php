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

namespace Espo\Modules\Sales\Tools\PaymentMandate;

use Espo\Modules\Sales\Entities\PaymentMandate;
use Espo\ORM\Entity;
use Espo\Core\Utils\Metadata;
use Espo\ORM\EntityManager;
use RuntimeException;

class RecordProvider
{
    public function __construct(
        private Metadata $metadata,
        private EntityManager $entityManager,
    ) {}

    public function get(PaymentMandate $channel): Entity
    {
        $type = $channel->getType();

        if (!$channel->isNew() || $channel->hasRecord()) {
            $record = $channel->getRecord();
        } else {
            $record = $this->prepare($type);
        }

        return $record;
    }

    public function prepare(string $type): Entity
    {
        $entityType = $this->metadata->get("app.salesPaymentMandates.$type.entityType");

        if (!is_string($entityType)) {
            throw new RuntimeException("Bad mandate type.");
        }

        return $this->entityManager->getNewEntity($entityType);
    }
}
