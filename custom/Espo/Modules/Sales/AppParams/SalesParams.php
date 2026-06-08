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

namespace Espo\Modules\Sales\AppParams;

use Espo\Modules\Sales\Entities\PriceBook;
use Espo\Modules\Sales\Tools\PaymentTermsProfile\DefaultPaymentTermsProfileProvider;
use Espo\Modules\Sales\Tools\Price\DefaultPriceBookProvider;
use Espo\Tools\App\AppParam;
use stdClass;

/**
 * @noinspection PhpUnused
 */
class SalesParams implements AppParam
{
    public function __construct(
        private DefaultPriceBookProvider $defaultPriceBookProvider,
        private DefaultPaymentTermsProfileProvider $defaultPaymentTermsProfileProvider,
    ) {}

    public function get(): stdClass
    {
        return (object) [
            'isTaxInclusivePrices' => $this->getDefaultPriceBook()?->isTaxInclusive() ?? false,
            'defaultPaymentTermsDays' => $this->getDefaultPaymentTermsDays(),
        ];
    }

    private function getDefaultPriceBook(): ?PriceBook
    {
        return $this->defaultPriceBookProvider->get();
    }

    private function getDefaultPaymentTermsDays(): ?int
    {
        $profile = $this->defaultPaymentTermsProfileProvider->get();

        if (!$profile) {
            return null;
        }

        $items = $profile->getItems();

        if ($items === []) {
            return null;
        }

        $last = $items[count($items) - 1];

        return $last->days;
    }
}
