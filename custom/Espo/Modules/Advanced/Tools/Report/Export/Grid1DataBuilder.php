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
use Espo\Modules\Advanced\Tools\Report\Export\Xlsx\RowType;
use Espo\Modules\Advanced\Tools\Report\Export\Xlsx\SheetData;
use Espo\Modules\Advanced\Tools\Report\GridType\Result;
use RuntimeException;
use stdClass;

class Grid1DataBuilder
{
    private const PRECISION = 2;

    public function __construct(
        private Language $language,
    ) {}

    public function build(Result $reportResult): SheetData
    {
        $hasSubListColumns = $reportResult->getSubListColumnList() !== [];
        $groupCount = count($reportResult->getGroupByList());
        $groupName = $reportResult->getGroupByList()[0] ?? '__STUB__';

        $rows = [];

        $cells = [
            new DataCell(
                value: $reportResult->getGroupNameMap()[$groupName] ?? null,
                type: CellType::HeadGroup,
            ),
        ];

        foreach ($reportResult->getColumnList() as $column) {
            $cells[] = new DataCell(
                value: $reportResult->getColumnNameMap()[$column] ?? null,
                type: $this->isNonSummary($reportResult, $column) ? CellType::HeadNonSummary : CellType::HeadSummary,
            );
        }

        $rows[] = new DataRow(
            cells: $cells,
            type: RowType::Header,
        );

        foreach ($reportResult->getGrouping()[0] ?? [] as $group) {
            $itemRows = $this->buildGroupRows($reportResult, $groupName, $group);

            $rows = [...$rows, ...$itemRows];
        }

        if ($groupCount) {
            $cells = [];

            $cells[] = new DataCell(
                value: $this->language->translateLabel('Total', 'labels', Report::ENTITY_TYPE),
                type: CellType::Label,
            );

            foreach ($reportResult->getColumnList() as $column) {
                if (
                    !in_array($column, $reportResult->getNumericColumnList()) ||
                    !in_array($column, $reportResult->getAggregatedColumnList())
                ) {
                    $cells[] = new DataCell(
                        value: null,
                        type: CellType::Empty,
                    );

                    continue;
                }

                $decimalPlaces = $reportResult->getColumnDecimalPlacesMap()->$column ?? null;
                $fieldType = $reportResult->getColumnTypeMap()[$column] ?? null;
                $function = Grid2NormalizedDataBuilder::getCellFunction($column);

                if ($fieldType === FieldType::INT && $function === CellFunction::Avg) {
                    $decimalPlaces = self::PRECISION;
                }

                $value = $reportResult->getSums()->$column ?? 0;

                $cells[] = new DataCell(
                    value: $value,
                    type: CellType::Total,
                    fieldType: $fieldType,
                    function: !$hasSubListColumns ? $function : null,
                    decimalPlaces: $decimalPlaces,
                );
            }

            $rows[] = new DataRow(
                cells: $cells,
                type: RowType::Total,
            );
        }

        $hasTotalFunctions = !$hasSubListColumns && $groupCount;

        return new SheetData(
            rows: $rows,
            firstSummaryRowNumber: $hasTotalFunctions ? 1 : null,
            lastSummaryRowNumber: $hasTotalFunctions ? count($rows) - 2 : null,
        );
    }

    private function isNonSummary(Result $reportResult, string $column): bool
    {
        return in_array($column, $reportResult->getNonSummaryColumnList());
    }

    /**
     * @return DataRow[]
     */
    private function buildGroupRows(Result $reportResult, string $groupName, mixed $group): array
    {
        $hasSubListColumns = $reportResult->getSubListColumnList() !== [];

        $rows = [];

        if (!$hasSubListColumns) {
            return [
                $this->buildGroupTotalRow($reportResult, $groupName, $group, true, true)
            ];
        }

        $rows[] = $this->buildGroupTotalRow($reportResult, $groupName, $group, false);

        foreach ($this->buildSubRows($reportResult, $group) as $row) {
            $rows[] = $row;
        }

        $rows[] = $this->buildGroupTotalRow($reportResult, $groupName, $group, true);

        return $rows;
    }

    private function buildGroupTotalRow(
        Result $reportResult,
        string $groupName,
        mixed $group,
        bool $onlyNumeric,
        bool $full = false,
    ): DataRow {

        $hasSubListColumns = $reportResult->getSubListColumnList() !== [];

        $cells = [];

        if (!$onlyNumeric || $full) {
            $cells[] = new DataCell(
                value:  $this->prepareGroupValue($reportResult, $groupName, $group),
                type: CellType::NonSummary,
                dateFunction: $this->getDateFunction($groupName),
            );
        } else {
            $cells[] = new DataCell(
                value: $this->language->translateLabel('Group Total', 'labels', 'Report'),
                type: CellType::Label,
            );
        }

        foreach ($reportResult->getColumnList() as $column) {
            $isNumericValue = in_array($column, $reportResult->getNumericColumnList());

            if (
                $hasSubListColumns && !$onlyNumeric && $isNumericValue ||
                $hasSubListColumns && $onlyNumeric && !$isNumericValue ||
                $isNumericValue && !in_array($column, $reportResult->getAggregatedColumnList())
            ) {
                $cells[] = new DataCell(
                    value: null,
                    type: CellType::Empty,
                );

                continue;
            }

            if ($isNumericValue) {
                $value = $reportResult->getReportData()->$group->$column ?? 0;

                $cells[] = new DataCell(
                    value: $value,
                    type: CellType::Summary,
                    fieldType: $reportResult->getColumnTypeMap()[$column] ?? null,
                    decimalPlaces: $reportResult->getColumnDecimalPlacesMap()->$column ?? null,
                );
            } else {
                $value = $reportResult->getReportData()->$group->$column ?? null;

                $cells[] = new DataCell(
                    value: $value,
                    type: CellType::Summary,
                    fieldType: $reportResult->getColumnTypeMap()[$column] ?? null,
                );
            }
        }

        return new DataRow(
            cells: $cells,
            type: $full || $onlyNumeric ? RowType::DataRow : RowType::HeadDataRow,
        );
    }

    private function prepareGroupValue(Result $reportResult, string $groupName, mixed $group): mixed
    {
        if ($group) {
            $label = $reportResult->getGroupValueMap()[$groupName][$group] ?? $group;
        } else {
            $label = $this->language->translateLabel('-Empty-', 'labels', 'Report');
        }

        return $label;
    }

    private function getDateFunction(string $column): ?DateFunction
    {
        return Grid2NormalizedDataBuilder::getDateFunction($column);
    }

    /**
     * @return DataRow[]
     */
    private function buildSubRows(Result $reportResult, mixed $group): array
    {
        $rows = [];

        foreach ($reportResult->getSubListData()->$group ?? [] as $item) {
            if (!$item instanceof stdClass) {
                throw new RuntimeException("Bad sub-list item.");
            }
            $rows[] = $this->buildSubRowItem($reportResult, $group, $item);
        }

        return $rows;
    }

    private function buildSubRowItem(Result $reportResult, mixed $group, stdClass $item): DataRow
    {
        $cells = [];

        $cells[] = new DataCell(
            value: null,
            type: CellType::Empty,
        );

        foreach ($reportResult->getColumnList() as $column) {
            if (!in_array($column, $reportResult->getSubListColumnList())) {
                $cells[] = new DataCell(
                    value: null,
                    type: CellType::Empty,
                );

                continue;
            }

            $isNumericValue = in_array($column, $reportResult->getNumericColumnList());

            if ($isNumericValue) {
                $value = $item->$column ?? 0;

                $cells[] = new DataCell(
                    value: $value,
                    type: CellType::NonSummary,
                    fieldType: $reportResult->getColumnTypeMap()[$column] ?? null,
                    decimalPlaces: $reportResult->getColumnDecimalPlacesMap()->$column ?? null,
                    isNumeric: true,
                );
            } else {
                $value = $item->$column ?? null;

                $cells[] = new DataCell(
                    value: $value,
                    type: CellType::NonSummary,
                    fieldType: $reportResult->getColumnTypeMap()[$column] ?? null,
                    isNumeric: false,
                );
            }
        }

        return new DataRow(
            cells: $cells,
            type: RowType::SubDataRow,
        );
    }
}
