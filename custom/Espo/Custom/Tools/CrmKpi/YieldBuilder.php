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

        return $rows;
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
                $buckets[$index] ?? self::emptyMetrics()
            );
        }

        return $rows;
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
    private static function buildPeriodRow(string $label, array $metrics): object
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
            'label' => $label,
            'cells' => $cells,
            'steps' => $steps,
        ];
    }

    private static function buildCell(object $step): object
    {
        $percents = [];

        if ($step->key === 'appuntamentiNetti' && $step->percentOfPrevious !== null) {
            $percents[] = $step->percentOfPrevious;
        }

        if ($step->percentOfNetti !== null) {
            $percents[] = $step->percentOfNetti;
        }

        if ($step->percentOfOpportunita !== null) {
            $percents[] = $step->percentOfOpportunita;
        }

        if ($step->key === 'contrattiNetti' && $step->percentOfPrevious !== null) {
            $percents[] = $step->percentOfPrevious;
        }

        return (object) [
            'value' => (int) $step->value,
            'percents' => $percents,
        ];
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

        if ($step->key === 'contrattiNetti' && $step->percentOfPrevious !== null) {
            $parts[] = $step->percentOfPrevious . '% prec';
        }

        return implode(' · ', $parts);
    }
}
