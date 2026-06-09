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

namespace Espo\Modules\Advanced\Tools\Report\Export;

use Espo\Modules\Advanced\Tools\Report\GridType\Helper;
use Espo\Modules\Advanced\Tools\Report\GridType\Result as GridResult;
use RuntimeException;
use stdClass;

class CellValueHelper
{
    public function __construct(
        private Helper $gridHelper,
    ) {}

    /**
     * Only for Grid-2.
     */
    public function getCellDisplayValueFromResult(
        int $groupIndex,
        string $groupValue,
        string $column,
        GridResult $reportResult,
    ): mixed {

        $groupName = $reportResult->getGroupByList()[$groupIndex];

        $dataMap = $reportResult->getNonSummaryData()->$groupName ?? null;

        if (!$dataMap instanceof stdClass) {
            throw new RuntimeException("No non-summary data for the group '$groupName'.");
        }

        $value = '';

        if ($this->gridHelper->isColumnNumeric($column, $reportResult)) {
            $value = 0;
        }

        if (
            property_exists($dataMap, $groupValue) &&
            property_exists($dataMap->$groupValue, $column)
        ) {
            $value = $dataMap->$groupValue->$column;
        }

        if (
            !$this->gridHelper->isColumnNumeric($column, $reportResult) &&
            !is_null($value)
        ) {
            if (property_exists($reportResult->getCellValueMaps(), $column)) {
                if (property_exists($reportResult->getCellValueMaps()->$column, $value)) {
                    $value = $reportResult->getCellValueMaps()->$column->$value;
                }
            }
        }

        if (is_null($value)) {
            $value = '';
        }

        return $value;
    }
}
