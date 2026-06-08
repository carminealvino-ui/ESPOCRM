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

namespace Espo\Modules\Sales\Tools\Invoice\EInvoice;

use Espo\Core\Field\Address;
use Espo\Core\Utils\Config;

class ConfigProvider
{
    public function __construct(
        private Config $config,
    ) {}

    public function getSellerCompanyName(): ?string
    {
        return $this->config->get('sellerCompanyName');
    }

    public function getSellerVatNumber(): ?string
    {
        return $this->config->get('sellerVatNumber');
    }

    public function getSellerElectronicAddressIdentifier(): ?string
    {
        return $this->config->get('sellerElectronicAddressIdentifier');
    }

    public function getSellerElectronicAddressScheme(): ?string
    {
        return $this->config->get('sellerElectronicAddressScheme');
    }

    public function getSellerTaxRegistrationIdentifier(): ?string
    {
        return $this->config->get('sellerTaxRegistrationIdentifier');
    }

    public function getSellerTaxRegistrationScheme(): ?string
    {
        return $this->config->get('sellerTaxRegistrationScheme');
    }

    public function getSellerContactEmailAddress(): ?string
    {
        return $this->config->get('sellerContactEmailAddress');
    }

    public function getSellerContactPhoneNumber(): ?string
    {
        return $this->config->get('sellerContactPhoneNumber');
    }

    public function getSellerContactName(): ?string
    {
        return $this->config->get('sellerContactName');
    }

    public function getSellerAddress(): Address
    {
        return Address::create()
            ->withStreet($this->config->get('sellerAddressStreet'))
            ->withCity($this->config->get('sellerAddressCity'))
            ->withState($this->config->get('sellerAddressState'))
            ->withPostalCode($this->config->get('sellerAddressPostalCode'))
            ->withCountry($this->config->get('sellerAddressCountry'));
    }
}
