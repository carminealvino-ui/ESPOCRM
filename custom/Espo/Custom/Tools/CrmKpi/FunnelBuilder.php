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
     * Pipeline vendita:
     * totali → lordi → netti (= opportunità) → contratti lordi → contratti netti.
     *
     * @return object[]
     */
    public static function buildSalesPipeline(
        float $appuntamentiTotali,
        float $appuntamentiLordi,
        float $appuntamentiNetti,
        float $contrattiLordi,
        float $contrattiNetti
    ): array {
        $steps = [
            ['key' => 'appuntamentiTotali', 'label' => 'Appuntamenti totali', 'value' => $appuntamentiTotali],
            ['key' => 'appuntamentiLordi', 'label' => 'Appuntamenti lordi', 'value' => $appuntamentiLordi],
            ['key' => 'appuntamentiNetti', 'label' => 'Netti (= Opportunità)', 'value' => $appuntamentiNetti],
            ['key' => 'contratti', 'label' => 'Contratti lordi', 'value' => $contrattiLordi],
            ['key' => 'contrattiNetti', 'label' => 'Contratti netti', 'value' => $contrattiNetti],
        ];

        $max = max($appuntamentiTotali, $appuntamentiLordi, $appuntamentiNetti, $contrattiLordi, $contrattiNetti, 1.0);
        $baseTotali = max($appuntamentiTotali, 1.0);
        $baseLordi = max($appuntamentiLordi, 1.0);
        $baseNetti = max($appuntamentiNetti, 1.0);

        $result = [];
        $previousValue = null;

        foreach ($steps as $step) {
            $value = (float) $step['value'];
            $percentOfPrevious = null;
            $percentOfTotali = null;
            $percentOfLordi = null;
            $percentOfNetti = null;

            if ($previousValue !== null) {
                $percentOfPrevious = self::ratioPercent($value, $previousValue);
            }

            if ($step['key'] === 'appuntamentiLordi') {
                $percentOfTotali = self::ratioPercent($value, $baseTotali);
            }

            if ($step['key'] === 'appuntamentiNetti') {
                $percentOfLordi = self::ratioPercent($value, $baseLordi);
                $percentOfTotali = self::ratioPercent($value, $baseTotali);
            }

            if (in_array($step['key'], ['contratti', 'contrattiNetti'], true)) {
                $percentOfLordi = self::ratioPercent($value, $baseLordi);
                $percentOfNetti = self::ratioPercent($value, $baseNetti);
            }

            $result[] = (object) [
                'key' => $step['key'],
                'label' => $step['label'],
                'value' => $value,
                'heightPercent' => round(($value / $max) * 100, 1),
                'percentOfTotali' => $percentOfTotali,
                'percentOfLordi' => $percentOfLordi,
                'percentOfNetti' => $percentOfNetti,
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
