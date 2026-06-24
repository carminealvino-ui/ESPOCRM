<?php

namespace Espo\Custom\Tools\CrmKpi;

class FunnelBuilder
{
    /**
     * @param array<int, array{key?: string, label: string, value: int|float}> $steps
     * @return object[]
     */
    public static function build(array $steps): array
    {
        if ($steps === []) {
            return [];
        }

        $base = max((float) ($steps[0]['value'] ?? 0), 1.0);
        $max = max(array_map(static fn (array $step): float => (float) ($step['value'] ?? 0), $steps));
        $max = max($max, 1.0);

        $result = [];
        $previousValue = null;

        foreach ($steps as $step) {
            $value = (float) ($step['value'] ?? 0);
            $percentOfTotal = round(($value / $base) * 100, 1);
            $percentOfPrevious = null;

            if ($previousValue !== null) {
                $percentOfPrevious = round(($value / max($previousValue, 1.0)) * 100, 1);
            }

            $item = (object) [
                'key' => $step['key'] ?? null,
                'label' => $step['label'],
                'value' => $value,
                'heightPercent' => round(($value / $max) * 100, 1),
                'percentOfTotal' => $percentOfTotal,
                'percentOfPrevious' => $percentOfPrevious,
            ];

            $result[] = $item;
            $previousValue = $value;
        }

        return $result;
    }
}
