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
 * License ID: 3fed3a66185f29dcda0e14c6ee94bf07
 ************************************************************************************/

namespace Espo\Modules\Advanced\Tools\Report\Internal;

use Espo\Core\Utils\Metadata;
use Espo\Modules\Advanced\Entities\Report;
use RuntimeException;

class InternalReportHelper
{
    public function __construct(
        private Metadata $metadata,
    ) {}

    public function populateFields(Report $report): void
    {
        if (!$report->getInternalClassName()) {
            throw new RuntimeException("Non-internal report.");
        }

        $reportParams = $this->getInternalParams($report->getInternalClassName());

        $report->set('entityType', $reportParams['entityType'] ?? null);
        $report->set('type', $reportParams['type'] ?? null);
        $report->set('depth', $reportParams['depth'] ?? null);
        $report->set('runtimeFilters', $reportParams['runtimeFilters'] ?? null);
        $report->set('columns', $reportParams['columns'] ?? null);
        $report->set('isInternal', true);
    }

    /**
     * @return array<string, mixed>
     */
    private function getInternalParams(string $name): array
    {
        return $this->metadata->get("app.advancedReport.internalReports.$name") ?? [];
    }
}
