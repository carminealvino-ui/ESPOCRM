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

namespace Espo\Modules\Sales\Tools\Sales;

use Espo\Core\Field\Link;
use Espo\Core\Utils\Config;

class ConfigDataProvider
{
    public function __construct(
        private Config $config
    ) {}

    public function isWarehousesEnabled(): bool
    {
        return (bool) $this->config->get('warehousesEnabled');
    }

    public function isInventoryTransactionsEnabled(): bool
    {
        return (bool) $this->config->get('inventoryTransactionsEnabled');
    }

    public function isPriceBooksEnabled(): bool
    {
        return (bool) $this->config->get('priceBooksEnabled');
    }

    public function isDeliveryOrdersEnabled(): bool
    {
        return true;
    }

    public function isReceiptOrdersEnabled(): bool
    {
        return true;
    }

    public function getDefaultPriceBookId(): ?string
    {
        return $this->config->get('defaultPriceBookId');
    }

    public function getDefaultPaymentTermsProfileId(): ?string
    {
        return $this->config->get('defaultPaymentTermsProfileId');
    }

    public function isIssuanceLockingEnabled(): bool
    {
        return $this->config->get('salesIssuanceLocking') ||
            $this->config->get('salesForceIssuanceLocking');
    }

    public function isInvoiceListPriceEnabled(): bool
    {
        return (bool) $this->config->get('salesInvoiceListPrice');
    }

    public function isPurchaseOrderListPriceEnabled(): bool
    {
        return (bool) $this->config->get('salesPurchaseOrderListPrice');
    }

    public function isTaxCodesEnabled(): bool
    {
        return (bool) $this->config->get('salesTaxCodesEnabled');
    }

    public function isDebitNoteNumberingDisabled(): bool
    {
        return (bool) $this->config->get('salesDebitNoteNumberingDisabled');
    }

    public function isDraftNumberingEnabled(): bool
    {
        return (bool) $this->config->get('salesDraftNumbering');
    }

    public function getSellerAddressCountry(): ?string
    {
        return $this->config->get('sellerAddressCountry');
    }

    public function getDefaultRoundingProfile(): ?Link
    {
        $id = $this->config->get('defaultRoundingProfileId');

        if (!$id) {
            return null;
        }

        return Link::create($id);
    }

    public function isSupplierBillPostingDateEnabled(): bool
    {
        return (bool) $this->config->get('supplierBillPostingDate');
    }

    public function isPaymentEntryPostingDateEnabled(): bool
    {
        return (bool) $this->config->get('paymentEntryPostingDate');
    }

    public function isProductLevelPricesEnabled(): bool
    {
        return (bool) $this->config->get('productLevelPricesEnabled');
    }
}
