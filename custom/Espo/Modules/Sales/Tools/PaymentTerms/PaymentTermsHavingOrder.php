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

use Espo\Core\Field\Date;
use Espo\Core\Field\Link;
use Espo\Modules\Sales\Entities\PaymentTermsProfile;

interface PaymentTermsHavingOrder
{
    public function getPaymentTermsNote(): ?string;

    public function setPaymentTermsNote(?string $note): PaymentTermsHavingOrder;

    public function getPaymentTermsProfile(): ?PaymentTermsProfile;

    public function setPaymentTermsProfile(PaymentTermsProfile|Link|null $profile): PaymentTermsHavingOrder;

    public function isPaymentTermsProfileChanged(): bool;

    public function isPaymentTermsToCalculate(): bool;

    /**
     * @return InstallmentLine[]
     */
    public function getInstallments(): array;

    public function clearInstallmentSaveItems(bool $start = false): void;

    public function addInstallmentSaveItem(InstallmentLine $line): void;

    /**
     * @return ?InstallmentLine[]
     */
    public function getInstallmentSaveItems(): ?array;

    public function setDateDue(?Date $date): PaymentTermsHavingOrder;
}
