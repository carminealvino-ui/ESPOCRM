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

use Espo\Core\ORM\Type\FieldType;
use Espo\Core\Utils\Language;
use Espo\Modules\Advanced\Entities\Report;
use Espo\Modules\Advanced\Tools\Report\Export\Xlsx\CellFunction;
use Espo\Modules\Advanced\Tools\Report\Export\Xlsx\CellType;
use Espo\Modules\Advanced\Tools\Report\Export\Xlsx\DataCell;
use Espo\Modules\Advanced\Tools\Report\Export\Xlsx\DataRow;
use Espo\Modules\Advanced\Tools\Report\Export\Xlsx\DateFunction;
use Espo\Modules\Advanced\Tools\Report\Export\Xlsx\SheetData;
use Espo\Modules\Advanced\Tools\Report\Export\Xlsx\RowType;
use Espo\Modules\Advanced\Tools\Report\GridType\Result;
use Espo\Modules\Advanced\Tools\Report\GridType\Result as GridResult;

class Grid2NormalizedDataBuilder
{
    private const PRECISION = 2;

    public function __construct(
        private Language $language,
        private CellValueHelper $cellValueHelper,
    ) {}

    public function build(Result $reportResult): SheetData
    {
        $groupName1 = $reportResult->getGroupByList()[0];
        $groupName2 = $reportResult->getGroupByList()[1];

        $rows = [];

        $cells = [
            new DataCell(
                value: $reportResult->getGroupNameMap()[$groupName1] ?? null,
                type: CellType::HeadGroup,
            ),
            new DataCell(
                value: $reportResult->getGroupNameMap()[$groupName2] ?? null,
                type: CellType::HeadGroup,
            ),
        ];

        foreach ($reportResult->getNonSummaryColumnList() as $column) {
            $cells[] = new DataCell(
                value: $reportResult->getColumnNameMap()[$column] ?? null,
                type: CellType::HeadNonSummary,
            );
        }

        foreach ($reportResult->getSummaryColumnList() as $column) {
            $cells[] = new DataCell(
                value: $reportResult->getColumnNameMap()[$column] ?? null,
                type: CellType::HeadSummary,
            );
        }

        $rows[] = new DataRow(
            cells: $cells,
            type: RowType::Header,
        );

        foreach ($reportResult->getGrouping()[0] ?? [] as $group) {
            foreach ($reportResult->getGrouping()[1] ?? [] as $secondGroup) {
                $cells = [];

                $cells[] = new DataCell(
                    value: $this->prepareGroupValue($reportResult, $groupName1, $group),
                    type: CellType::NonSummary,
                    dateFunction: self::getDateFunction($groupName1),
                );

                $cells[] = new DataCell(
                    value: $this->prepareGroupValue($reportResult, $groupName2, $secondGroup),
                    type: CellType::NonSummary,
                    dateFunction: self::getDateFunction($groupName2),
                );

                foreach ($reportResult->getNonSummaryColumnList() as $column) {
                    $columnGroup = $reportResult->getNonSummaryColumnGroupMap()->$column ?? null;

                    $columnGroupValue = $columnGroup === $reportResult->getGroupByList()[0] ?
                        $group : $secondGroup;

                    $cells[] = new DataCell(
                        value: $this->getCellDisplayValueFromResult(
                            groupIndex: $columnGroup === $reportResult->getGroupByList()[0] ? 0 : 1,
                            groupValue: $columnGroupValue,
                            column: $column,
                            reportResult: $reportResult,
                        ),
                        type: CellType::NonSummary,
                        fieldType: $reportResult->getColumnTypeMap()[$column] ?? null,
                        decimalPlaces: $reportResult->getColumnDecimalPlacesMap()->$column ?? null,
                    );
                }

                $hasNonEmpty = false;

                foreach ($reportResult->getSummaryColumnList() as $column) {
                    $value = $reportResult->getReportData()->$group->$secondGroup->$column ?? null;

                    if ($value !== null) {
                        $hasNonEmpty = true;
                    }

                    $decimalPlaces = $reportResult->getColumnDecimalPlacesMap()->$column ?? null;
                    $fieldType = $reportResult->getColumnTypeMap()[$column] ?? null;

                    $cells[] = new DataCell(
                        value: $value,
                        type: CellType::Summary,
                        fieldType: $fieldType,
                        decimalPlaces: $decimalPlaces,
                    );
                }

                if (!$hasNonEmpty) {
                    continue;
                }

                $rows[] = new DataRow(
                    cells: $cells,
                    type: RowType::DataRow,
                );
            }
        }

        $cells = [];

        $cells[] = new DataCell(
            value: $this->language->translateLabel('Total', 'labels', Report::ENTITY_TYPE),
            type: CellType::Label,
        );

        $cells[] = new DataCell(
            value: null,
            type: CellType::Empty,
        );

        foreach ($reportResult->getNonSummaryColumnList() as $ignored) {
            $cells[] = new DataCell(
                value: null,
                type: CellType::Empty,
            );
        }

        foreach ($reportResult->getSummaryColumnList() as $column) {
            $value = $reportResult->getSums()->$column ?? 0;

            $decimalPlaces = $reportResult->getColumnDecimalPlacesMap()->$column ?? null;
            $fieldType = $reportResult->getColumnTypeMap()[$column] ?? null;
            $function = self::getCellFunction($column);

            if ($fieldType === FieldType::INT && $function === CellFunction::Avg) {
                $decimalPlaces = self::PRECISION;
            }

            $cells[] = new DataCell(
                value: $value,
                type: CellType::Total,
                fieldType: $fieldType,
                function: $function,
                decimalPlaces: $decimalPlaces,
            );
        }

        $rows[] = new DataRow(
            cells: $cells,
            type: RowType::Total,
        );

        return new SheetData(
            rows: $rows,
            firstSummaryRowNumber: 1,
            lastSummaryRowNumber: count($rows) - 2,
        );
    }

    private function getCellDisplayValueFromResult(
        int $groupIndex,
        string $groupValue,
        string $column,
        GridResult $reportResult,
    ): mixed {

        return $this->cellValueHelper->getCellDisplayValueFromResult(
            groupIndex: $groupIndex,
            groupValue: $groupValue,
            column: $column,
            reportResult: $reportResult,
        );
    }

    private function prepareGroupValue(Result $reportResult, string $groupName, mixed $group): string
    {
        if (!$group) {
            return $this->language->translateLabel('-Empty-', 'labels', 'Report');
        }

        if (isset($reportResult->getGroupValueMap()[$groupName][$group])) {
            return $reportResult->getGroupValueMap()[$groupName][$group];
        }

        return (string) $group;
    }

    public static function getCellFunction(string $column): ?CellFunction
    {
        [$function] = explode(':', $column);

        if ($function === 'COUNT') {
            return CellFunction::Sum;
        }

        if ($function === 'SUM') {
            return CellFunction::Sum;
        }

        if ($function === 'AVG') {
            return CellFunction::Avg;
        }

        if ($function === 'MIN') {
            return CellFunction::Min;
        }

        if ($function === 'MAX') {
            return CellFunction::Max;
        }

        return null;
    }

    public static function getDateFunction(string $column): ?DateFunction
    {
        if (!str_contains($column, ':')) {
            return null;
        }

        [$f,] = explode(':', $column);

        return match ($f) {
            'MONTH' => DateFunction::Month,
            'YEAR' => DateFunction::Year,
            'DAY' => DateFunction::Day,
            default => null,
        };
    }
}
