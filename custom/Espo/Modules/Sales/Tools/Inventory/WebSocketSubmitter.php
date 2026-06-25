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

namespace Espo\Modules\Sales\Tools\Inventory;

use Espo\Core\Utils\Config;
use Espo\Core\WebSocket\Submission;
use Espo\Modules\Sales\Entities\DeliveryOrder;
use Espo\Modules\Sales\Entities\InventoryAdjustment;
use Espo\Modules\Sales\Entities\PurchaseOrder;
use Espo\Modules\Sales\Entities\ReceiptOrder;
use Espo\Modules\Sales\Entities\SalesOrder;
use Espo\Modules\Sales\Entities\TransferOrder;

class WebSocketSubmitter
{
    public function __construct(
        private Submission $submission,
        private Config $config
    ) {}

    public function process(
        SalesOrder|PurchaseOrder|DeliveryOrder|ReceiptOrder|TransferOrder|InventoryAdjustment $entity
    ): void {

        if (!$this->toSubmit()) {
            return;
        }

        $productIds = $entity->getInventoryProductIds();

        if ($productIds === []) {
            return;
        }

        $this->submission->submit('inventoryQuantityUpdate', null, (object) ['productIds' => $productIds]);

        foreach ($productIds as $productId) {
            $this->submission->submit("recordUpdate.Product.$productId");
        }
    }

    private function toSubmit(): bool
    {
        if (!$this->config->get('useWebSocket')) {
            return false;
        }

        return true;
    }
}
