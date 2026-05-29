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

namespace Espo\Modules\Sales\Tools\PaymentTerms;

use Espo\Core\Currency\CalculatorUtil;
use Espo\Core\Field\Currency;
use Espo\Core\Field\Date;
use Espo\Modules\Sales\Entities\PaymentInstallment;

readonly class InstallmentLine
{
    /**
     * @param numeric-string $percentage
     */
    public function __construct(
        public Date $date,
        public Currency $amount,
        public Currency $amountLocal,
        public string $percentage,
        public ?string $status = null,
    ) {}

    public static function fromEntity(PaymentInstallment $entity): self
    {
        return new InstallmentLine(
            date: $entity->getDate(),
            amount: $entity->getAmount(),
            amountLocal: $entity->getAmountLocal(),
            percentage: $entity->getPercentage(),
            status: $entity->getStatus(),
        );
    }

    /**
     * Omits status check.
     */
    public function isEqualTo(InstallmentLine $item): bool
    {
        if (
            $this->amount->getCode() !== $item->amount->getCode() ||
            $this->amountLocal->getCode() !== $item->amountLocal->getCode()
        ) {
            return false;
        }

        return $item->date->isEqualTo($this->date) &&
            $item->amount->compare($this->amount) === 0 &&
            $item->amountLocal->compare($this->amountLocal) === 0 &&
            CalculatorUtil::compare($item->percentage, $this->percentage) === 0;
    }
}
