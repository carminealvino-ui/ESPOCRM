<?php

namespace Espo\Custom\Tools\CrmKpi;

class YieldBuilder
{
  /** @var string[] */
    private const WEEKDAY_LABELS = [
        1 => 'Lun',
        2 => 'Mar',
        3 => 'Mer',
        4 => 'Gio',
        5 => 'Ven',
        6 => 'Sab',
        7 => 'Dom',
    ];

  /** @var string[] */
    private const PIPELINE_COLORS = [
        '#63a7c2',
        '#ccc058',
        '#c96947',
        '#b770e0',
        '#5cb85c',
    ];

    /** @var array<int, array{key: string, label: string}> */
    private const PIPELINE_COLUMNS = [
        ['key' => 'appuntamentiLordi', 'label' => 'Lordi'],
        ['key' => 'appuntamentiNetti', 'label' => 'Netti'],
        ['key' => 'opportunita', 'label' => 'Opp.'],
        ['key' => 'contratti', 'label' => 'Contr.'],
        ['key' => 'contrattiNetti', 'label' => 'C. netti'],
    ];

    /**
     * @return object[]
     */
    public static function pipelineColumns(): array
    {
        $columns = [];

        foreach (self::PIPELINE_COLUMNS as $column) {
            $columns[] = (object) $column;
        }

        return $columns;
    }

    /**
     * @param array<int, array<string, int>> $buckets
     * @return object[]
     */
    public static function buildWeekdayRows(array $buckets): array
    {
        $rows = [];

        foreach (self::WEEKDAY_LABELS as $day => $label) {
            $rows[] = self::buildPeriodRow($label, $buckets[$day] ?? self::emptyMetrics());
        }

        return self::applyColumnSharePercents($rows);
    }

    /**
     * @param array<int, array<string, int>> $buckets
     * @param array<int, array{index: int, start: string, end: string, label: string}> $weeks
     * @return object[]
     */
    public static function buildWeekRows(array $buckets, array $weeks): array
    {
        if ($weeks === []) {
            $weeks = WeekOfMonth::validWeeksForRange(null, null);
        }

        $rows = [];

        foreach ($weeks as $index => $week) {
            $rows[] = self::buildPeriodRow(
                $week['label'],
                $buckets[$index] ?? self::emptyMetrics(),
                'Sett. ' . ($week['index'] ?? $index),
            );
        }

        return self::applyColumnSharePercents($rows);
    }

    /**
     * @return object[]
     */
    public static function emptyWeekdayRows(): array
    {
        return self::buildWeekdayRows([]);
    }

    /**
     * @return object[]
     */
    public static function emptyWeekRows(): array
    {
        return self::buildWeekRows([], WeekOfMonth::validWeeksForRange(null, null));
    }

    /**
     * @return array<string, int>
     */
    public static function emptyMetrics(): array
    {
        return [
            'appuntamentiLordi' => 0,
            'appuntamentiNetti' => 0,
            'opportunita' => 0,
            'contratti' => 0,
            'contrattiNetti' => 0,
        ];
    }

    /**
     * @param array<string, int> $metrics
     */
    private static function buildPeriodRow(string $label, array $metrics, ?string $shortLabel = null): object
    {
        $pipeline = FunnelBuilder::buildSalesPipeline(
            (float) $metrics['appuntamentiLordi'],
            (float) $metrics['appuntamentiNetti'],
            (float) $metrics['opportunita'],
            (float) $metrics['contratti'],
            (float) $metrics['contrattiNetti'],
        );

        $steps = [];
        $cells = [];

        foreach ($pipeline as $index => $step) {
            $steps[] = (object) [
                'key' => $step->key,
                'label' => $step->label,
                'value' => (int) $step->value,
                'color' => self::PIPELINE_COLORS[$index] ?? self::PIPELINE_COLORS[0],
                'meta' => self::formatStepMeta($step),
            ];
            $cells[] = self::buildCell($step);
        }

        return (object) [
            'label' => $shortLabel ?? $label,
            'labelFull' => $shortLabel !== null ? $label : null,
            'cells' => $cells,
            'steps' => $steps,
        ];
    }

    private static function buildCell(object $step): object
    {
        return (object) [
            'value' => (int) $step->value,
            'percents' => [],
        ];
    }

    /**
     * Percentuale di ogni cella sul totale della colonna (le righe sommano a 100%).
     *
     * @param object[] $rows
     * @return object[]
     */
    private static function applyColumnSharePercents(array $rows): array
    {
        if ($rows === []) {
            return $rows;
        }

        $columnCount = count(self::PIPELINE_COLUMNS);
        $columnTotals = array_fill(0, $columnCount, 0);

        foreach ($rows as $row) {
            foreach ($row->cells as $index => $cell) {
                if ($index >= $columnCount) {
                    continue;
                }

                $columnTotals[$index] += (int) ($cell->value ?? 0);
            }
        }

        foreach ($rows as $row) {
            foreach ($row->cells as $index => $cell) {
                $total = $columnTotals[$index] ?? 0;
                $value = (int) ($cell->value ?? 0);

                if ($total <= 0) {
                    $cell->percents = [];
                    continue;
                }

                $cell->percents = [round(($value / $total) * 100, 1)];
            }
        }

        return $rows;
    }

    private static function formatStepMeta(object $step): string
    {
        $parts = [];

        if ($step->key === 'appuntamentiNetti' && $step->percentOfPrevious !== null) {
            $parts[] = $step->percentOfPrevious . '% su lordi';
        }

        if ($step->percentOfNetti !== null) {
            $parts[] = $step->percentOfNetti . '% su app. netti';
        }

        if ($step->percentOfOpportunita !== null) {
            $parts[] = $step->percentOfOpportunita . '% su opp.';
        }

        return implode(' · ', $parts);
    }
}
