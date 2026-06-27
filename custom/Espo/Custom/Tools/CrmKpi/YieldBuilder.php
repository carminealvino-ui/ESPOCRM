<?php

namespace Espo\Custom\Tools\CrmKpi;

class YieldBuilder
{
  /** @var array<int, string> */
    private const WEEKDAY_LABELS = [
        1 => 'Lun',
        2 => 'Mar',
        3 => 'Mer',
        4 => 'Gio',
        5 => 'Ven',
        6 => 'Sab',
        7 => 'Dom',
    ];

    /**
     * @param array<int, int> $lordi
     * @param array<int, int> $netti
     * @return object[]
     */
    public static function buildWeekdayRows(array $lordi, array $netti): array
    {
        $rows = [];

        foreach (self::WEEKDAY_LABELS as $day => $label) {
            $rows[] = self::buildRow(
                $label,
                (int) ($lordi[$day] ?? 0),
                (int) ($netti[$day] ?? 0)
            );
        }

        return self::applyBarWidths($rows);
    }

    /**
     * @param array<int, int> $lordi
     * @param array<int, int> $netti
     * @param array<int, array{index: int, start: string, end: string, label: string}> $weeks
     * @return object[]
     */
    public static function buildWeekRows(array $lordi, array $netti, array $weeks): array
    {
        if ($weeks === []) {
            $weeks = WeekOfMonth::validWeeksForRange(null, null);
        }

        $rows = [];

        foreach ($weeks as $index => $week) {
            $rows[] = self::buildRow(
                $week['label'],
                (int) ($lordi[$index] ?? 0),
                (int) ($netti[$index] ?? 0)
            );
        }

        return self::applyBarWidths($rows);
    }

    /**
     * @return object[]
     */
    public static function emptyWeekdayRows(): array
    {
        return self::buildWeekdayRows([], []);
    }

    /**
     * @return object[]
     */
    public static function emptyWeekRows(): array
    {
        return self::buildWeekRows([], [], WeekOfMonth::validWeeksForRange(null, null));
    }

  /**
     * @return object{label: string, lordi: int, netti: int, yieldPercent: float, widthPercent: float, meta: string}
     */
    private static function buildRow(string $label, int $lordi, int $netti): object
    {
        $yieldPercent = $lordi > 0 ? round(($netti / $lordi) * 100, 1) : 0.0;

        return (object) [
            'label' => $label,
            'lordi' => $lordi,
            'netti' => $netti,
            'yieldPercent' => $yieldPercent,
            'widthPercent' => 0.0,
            'meta' => $netti . ' netti / ' . $lordi . ' lordi',
        ];
    }

    /**
     * @param object[] $rows
     * @return object[]
     */
    private static function applyBarWidths(array $rows): array
    {
        $maxYield = 1.0;

        foreach ($rows as $row) {
            $maxYield = max($maxYield, (float) $row->yieldPercent);
        }

        foreach ($rows as $row) {
            $row->widthPercent = round(((float) $row->yieldPercent / $maxYield) * 100, 1);
        }

        return $rows;
    }
}
