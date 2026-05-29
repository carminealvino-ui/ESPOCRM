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

use Espo\Core\Select\Where\Item as WhereItem;
use stdClass;

class Data
{
    public const COLUMN_TYPE_SUMMARY = 'Summary';
    public const TABLE_MODE_REGULAR = 'Regular';
    public const TABLE_MODE_NORMALIZED = 'Normalized';

    /** @var string[] */
    private array $aggregatedColumns = [];
    private stdClass $columnsData;

    /**
     * @param string[] $columns
     * @param string[] $groupBy
     * @param string[] $orderBy
     * @param ?string[] $chartColors
     * @param ?stdClass[] $chartDataList
     */
    public function __construct(
        private string $entityType,
        private array $columns,
        private array $groupBy,
        private array $orderBy,
        private bool $applyAcl = false,
        private ?WhereItem $filtersWhere = null,
        private ?string $chartType = null,
        private ?array $chartColors = null,
        private ?string $chartColor = null,
        private ?array $chartDataList = null,
        private ?string $success = null,
        ?stdClass $columnsData = null,
        private ?string $tableMode = null,
    ) {
        $this->columnsData = $columnsData ?? (object) [];
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function getSuccess(): ?string
    {
        return $this->success;
    }

    /**
     * @return string[]
     */
    public function getOrderBy(): array
    {
        return $this->orderBy;
    }

    /**
     * @return string[]
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * @return string[]
     */
    public function getGroupBy(): array
    {
        return $this->groupBy;
    }

    public function applyAcl(): bool
    {
        return $this->applyAcl;
    }

    public function getFiltersWhere(): ?WhereItem
    {
        return $this->filtersWhere;
    }

    public function getChartType(): ?string
    {
        return $this->chartType;
    }

    /**
     * @return ?string[]
     */
    public function getChartColors(): ?array
    {
        return $this->chartColors;
    }

    public function getChartColor(): ?string
    {
        return $this->chartColor;
    }

    /**
     * @return ?stdClass[]
     */
    public function getChartDataList(): ?array
    {
        return $this->chartDataList;
    }

    /**
     * @return string[]
     */
    public function getAggregatedColumns(): array
    {
        return $this->aggregatedColumns;
    }

    public function getColumnLabel(string $column): ?string
    {
        if (!isset($this->columnsData->$column)) {
            return null;
        }

        $item = $this->columnsData->$column;

        if (!is_object($item)) {
            return null;
        }

        return $item->label ?? null;
    }

    public function getColumnType(string $column): ?string
    {
        if (!isset($this->columnsData->$column)) {
            return null;
        }

        $item = $this->columnsData->$column;

        if (!is_object($item)) {
            return null;
        }

        return $item->type ?? null;
    }

    public function getColumnDecimalPlaces(string $column): ?int
    {
        if (!isset($this->columnsData->$column)) {
            return null;
        }

        $item = $this->columnsData->$column;

        if (!is_object($item)) {
            return null;
        }

        return $item->decimalPlaces ?? null;
    }

    /**
     * @param string[] $aggregatedColumns
     */
    public function withAggregatedColumns(array $aggregatedColumns): self
    {
        $obj = clone $this;
        $obj->aggregatedColumns = $aggregatedColumns;

        return $obj;
    }

    public function getTableMode(): ?string
    {
        return $this->tableMode;
    }
}
