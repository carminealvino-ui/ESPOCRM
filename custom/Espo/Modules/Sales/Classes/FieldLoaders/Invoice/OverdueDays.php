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

namespace Espo\Modules\Sales\Classes\FieldLoaders\Invoice;

use Espo\Core\FieldProcessing\Loader;
use Espo\Core\FieldProcessing\Loader\Params;
use Espo\Core\Utils\DateTime;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Entities\PaymentInstallment;
use Espo\ORM\Entity;

/**
 * @implements Loader<Invoice>
 */
class OverdueDays implements Loader
{
    private const FIELD = 'overdueDays';

    public function __construct(
        private DateTime $dateTime,
    ) {}

    public function process(Entity $entity, Params $params): void
    {
        if ($entity->has(self::FIELD)) {
            // Loaded with the additional applier.
            return;
        }

        if (!$entity->isIssued() || $entity->isNotActual()) {
            /** @noinspection PhpRedundantOptionalArgumentInspection */
            $entity->set(self::FIELD, null);

            return;
        }

        $today = $this->dateTime->getToday();

        $maxDays = null;

        foreach ($entity->getInstallmentCollection() as $item) {
            if ($item->getStatus() === PaymentInstallment::STATUS_SETTLED) {
                continue;
            }

            $diff = $item->getDate()->diff($today);

            $days = (int) $diff->days;

            if ($diff->invert) {
                $days = - $days;
            }

            if ($maxDays === null || $days > $maxDays) {
                $maxDays = $days;
            }
        }

        $entity->set(self::FIELD, $maxDays);
    }
}
