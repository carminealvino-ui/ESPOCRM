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

namespace Espo\Modules\Sales\Tools\ReceiptOrder;

use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Utils\Metadata;
use Espo\Modules\Sales\Entities\ReceiptOrder;
use Espo\Modules\Sales\Entities\TransferOrder;

class ReceivedQuantityCheck
{
    public function __construct(
        private Metadata $metadata
    ) {}

    /**
     * @throws Conflict
     */
    public function validate(ReceiptOrder|TransferOrder $entity): void
    {
        if (!in_array($entity->getStatus(), $this->getDoneStatusList($entity->getEntityType()))) {
            return;
        }

        foreach ($entity->getItems() as $item) {
            if ($item->getQuantityReceived() !== null) {
                continue;
            }

            throw Conflict::createWithBody(
                'Quantity received is empty.',
                Body::create()
                    ->withMessageTranslation('emptyQuantityReceived', ReceiptOrder::ENTITY_TYPE)
                    ->encode()
            );
        }
    }

    /**
     * @return string[]
     */
    private function getDoneStatusList(string $entityType): array
    {
        return $this->metadata->get("scopes.$entityType.doneStatusList") ?? [];
    }
}
