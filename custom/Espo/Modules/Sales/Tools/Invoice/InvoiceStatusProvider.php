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

namespace Espo\Modules\Sales\Tools\Invoice;

use Espo\Core\Utils\Metadata;
use Espo\Modules\Sales\Entities\Invoice;
use RuntimeException;

class InvoiceStatusProvider
{
    public function __construct(
        private Metadata $metadata,
    ) {}

    /**
     * @return string[]
     */
    public function getOpenIssued(): array
    {
        $list = array_diff(
            $this->getStatuses(),
            $this->getPreIssue(),
            $this->getDone(),
            $this->getCanceled(),
        );

        return array_values($list);
    }

    /**
     * @return string[]
     */
    public function getNotOpen(): array
    {
        return [
            ...$this->getPreIssue(),
            ...$this->getDone(),
            ...$this->getCanceled(),
        ];
    }

    public function getDraft(): string
    {
        if (!in_array(Invoice::STATUS_DRAFT, $this->getStatuses())) {
            throw new RuntimeException("No Draft invoice status.");
        }

        return Invoice::STATUS_DRAFT;
    }

    public function getFirstIssued(): string
    {
        $notOpenStatuses = $this->getNotOpen();

        foreach ($this->getStatuses() as $status) {
            if (!in_array($status, $notOpenStatuses)) {
                return $status;
            }
        }

        throw new RuntimeException("No any open invoice status.");
    }

    /**
     * @return string[]
     */
    public function getDone(): array
    {
        return $this->metadata->get("scopes.Invoice.doneStatusList", []);
    }

    /**
     * @return string[]
     */
    public function getCanceled(): array
    {
        return $this->metadata->get("scopes.Invoice.canceledStatusList", []);
    }

    /**
     * @return string[]
     */
    public function getPreIssue(): array
    {
        return $this->metadata->get("scopes.Invoice.preIssueStatusList", []);
    }

    /**
     * @return string[]
     */
    private function getStatuses(): array
    {
        /** @var string[] */
        return $this->metadata->get("entityDefs.Invoice.fields.status.options") ?? [];
    }
}
