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

namespace Espo\Modules\Advanced\Tools\Report\GridType;

use Espo\Core\Select\Where\Item;
use Espo\Modules\Advanced\Tools\Report\GridType\Data\ColumnType;
use Espo\Modules\Advanced\Tools\Report\GridType\Data\Rows;

/**
 * @noinspection PhpUnused
 */
class ResultPreparator
{
    public function __construct(
        private ResultHelper $resultHelper,
        private GridBuilder $gridBuilder,
    ) {}

    /**
     * Prepares a result object for a Grid report with one group-by.
     *
     * @param Rows $rows A query results.
     * @param ?Item $where Runtime filters.
     */
    public function prepare(ResultData $resultData, Rows $rows, ?Item $where = null): Result
    {
        $groupBy = $resultData->group->name;
        $secondGroupBy = $resultData->secondGroup->name ?? null;

        $groupValues = [];

        $rows = $rows->toAssocList();

        foreach ($rows as $row) {
            $groupValues[] = $row[$groupBy] ?? null;
        }

        $grouping = [$groupValues];

        $groupList = [$groupBy];

        if ($secondGroupBy) {
            $groupList[] = $secondGroupBy;

            $secondGroupValues = [];

            foreach ($rows as $row) {
                $secondGroupValues[] = $row[$secondGroupBy] ?? null;
            }

            $grouping = [$groupValues, $secondGroupValues];
        }

        $columnList = [];
        $numericColumnList = [];
        $summaryColumnList = [];
        $nonSummaryColumnList = [];
        $aggregatedColumnList = [];
        $columnNameMap = [];
        $columnTypeMap = [];

        $columnData = (object) [];

        foreach ($resultData->columns as $item) {
            $columnList[] = $item->name;

            $columnNameMap[$item->name] = $item->label;
            $columnTypeMap[$item->name] = $item->fieldType;

            if ($item->isNumeric) {
                $numericColumnList[] = $item->name;
            }

            if ($item->isAggregated) {
                $aggregatedColumnList[] = $item->name;

                if ($item->type !== ColumnType::Summary) {
                    $nonSummaryColumnList[] = $item->name;
                }
            }

            if ($item->type === ColumnType::Summary) {
                $summaryColumnList[] = $item->name;
            }

            $columnData->{$item->name} = (object) [
                'type' => $item->type->value,
            ];
        }

        $orderByList = [];

        foreach ($resultData->orders as $order) {
            $orderByList[] = $order->direction->value . ':' . $order->column;
        }

        $data = new Data(
            entityType: $resultData->entityType,
            columns: $columnList,
            groupBy: $groupList,
            orderBy: $orderByList,
            columnsData: $columnData,
            tableMode: $resultData->tableMode,
        );

        $groupValueMap = [];
        $emptyStringGroupExcluded = false;
        $sums = [];

        $this->resultHelper->fixRows($rows, $groupList, $emptyStringGroupExcluded);
        $this->resultHelper->populateGrouping($data, $groupList, $rows, $where, $grouping);
        $this->resultHelper->populateRows($data, $groupList, $grouping, $rows, []);
        $this->resultHelper->populateGroupValueMapForDateFunctions($data, $grouping, $groupValueMap);

        $reportData = $this->gridBuilder->build($data, $rows, $groupList, $columnList, $sums);

        $cellValueMaps = (object) [];
        $nonSummaryColumnGroupMap = (object) [];
        $nonSummaryData = null;

        if (count($groupList) === 2 && $nonSummaryColumnList) {
            $nonSummaryData = $this->gridBuilder->buildNonSummary(
                columnList: $data->getColumns(),
                summaryColumnList: $summaryColumnList,
                data: $data,
                rows: $rows,
                groupList: $groupList,
                cellValueMaps: $cellValueMaps,
                nonSummaryColumnGroupMap: $nonSummaryColumnGroupMap,
            );
        }

        if ($resultData->group->valueLabelKey) {
            $groupValueMap =
                $this->prepareGroupValueMap($rows, $resultData->group->name, $resultData->group->valueLabelKey);
        }

        if ($resultData->secondGroup?->valueLabelKey) {
            $secondGroupValueMap =
                $this->prepareGroupValueMap($rows, $resultData->secondGroup->name, $resultData->secondGroup->valueLabelKey);

            $groupValueMap = array_merge($groupValueMap, $secondGroupValueMap);
        }

        $subReportSwitchDataList = null;

        if ($resultData->switchItems) {
            foreach ($resultData->switchItems as $item) {
                $subReportSwitchDataList[] = (object) [
                    'name' => $item->name,
                    'label' => $item->label,
                    'entityType' => $item->entityType,
                ];
            }
        }

        $groupNameMap = [$groupBy => $resultData->group->label];

        if ($resultData->secondGroup) {
            $groupNameMap[$resultData->secondGroup->name] = $resultData->secondGroup->label;
        }

        $result = new Result(
            entityType: $resultData->entityType,
            groupByList: $groupList,
            columnList: $columnList,
            numericColumnList: $numericColumnList,
            summaryColumnList: $summaryColumnList,
            nonSummaryColumnList: $nonSummaryColumnList,
            aggregatedColumnList: $aggregatedColumnList,
            nonSummaryColumnGroupMap: $nonSummaryColumnGroupMap,
            sums: (object) $sums,
            groupValueMap: $groupValueMap,
            columnNameMap: $columnNameMap,
            columnTypeMap: $columnTypeMap,
            cellValueMaps: $cellValueMaps,
            grouping: $grouping,
            reportData: $reportData,
            nonSummaryData: $nonSummaryData,
            chartType: $resultData->chartType,
            emptyStringGroupExcluded: $emptyStringGroupExcluded,
            noSubReport: $resultData->noSubReport,
            currency: $resultData->currency,
            groupNameMap: $groupNameMap,
            subReportSwitchDataList: $subReportSwitchDataList,
            tableMode: $resultData->tableMode,
        );

        if ($resultData->secondGroup) {
            $this->resultHelper->calculateSums($data, $result);
        }

        return $result;
    }

    /**
     * @param array<string, mixed>[] $rows
     * @param string $groupByAlias
     * @return array<string, mixed>
     */
    private function prepareGroupValueMap(array $rows, string $groupByAlias, string $key): array
    {
        $groupValueMap = [];

        foreach ($rows as $row) {
            $name = $row[$key] ?? null;

            if ($name) {
                $groupValueMap[$groupByAlias] ??= [];
                $groupValueMap[$groupByAlias][$row[$groupByAlias]] = $name;
            }
        }

        return $groupValueMap;
    }
}
