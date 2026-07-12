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

    /**
     * Pipeline vendita: % su appuntamenti netti (opportunità) e su opportunità (contratti).
     *
     * @return object[]
     */
    public static function buildSalesPipeline(
        float $appuntamentiLordi,
        float $appuntamentiNetti,
        float $opportunita,
        float $contratti,
        float $contrattiNetti
    ): array {
        $steps = [
            ['key' => 'appuntamentiLordi', 'label' => 'Appuntamenti lordi', 'value' => $appuntamentiLordi],
            ['key' => 'appuntamentiNetti', 'label' => 'Appuntamenti netti', 'value' => $appuntamentiNetti],
            ['key' => 'opportunita', 'label' => 'Opportunità', 'value' => $opportunita],
            ['key' => 'contratti', 'label' => 'Contratti', 'value' => $contratti],
            ['key' => 'contrattiNetti', 'label' => 'Contratti netti', 'value' => $contrattiNetti],
        ];

        $max = max($appuntamentiLordi, $appuntamentiNetti, $opportunita, $contratti, $contrattiNetti, 1.0);

        $result = [];
        $previousValue = null;

        foreach ($steps as $step) {
            $value = (float) $step['value'];
            $percentOfPrevious = null;
            $percentOfNetti = null;
            $percentOfOpportunita = null;

            if ($previousValue !== null) {
                $percentOfPrevious = self::ratioPercent($value, $previousValue);
            }

            if ($step['key'] === 'opportunita') {
                $percentOfNetti = self::ratioPercent($value, $appuntamentiNetti);
            }

            if (in_array($step['key'], ['contratti', 'contrattiNetti'], true)) {
                $percentOfOpportunita = self::ratioPercent($value, $opportunita);
            }

            $result[] = (object) [
                'key' => $step['key'],
                'label' => $step['label'],
                'value' => $value,
                'heightPercent' => round(($value / $max) * 100, 1),
                'percentOfNetti' => $percentOfNetti,
                'percentOfOpportunita' => $percentOfOpportunita,
                'percentOfPrevious' => $percentOfPrevious,
            ];

            $previousValue = $value;
        }

        return $result;
    }

    /**
     * Efficacia: 0% se il valore e' 0; null se manca la base (niente % fuorvianti).
     */
    private static function ratioPercent(float $value, float $base): ?float
    {
        if ($value <= 0) {
            return 0.0;
        }

        if ($base <= 0) {
            return null;
        }

        return round(($value / $base) * 100, 1);
    }
}
