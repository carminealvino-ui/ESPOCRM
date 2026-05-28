<?php

namespace Espo\Custom\Services;

use DateTime;
use DateTimeImmutable;

/**
 * Date di competenza e liquidazione provvigionale in base al regime (brand/categoria).
 */
class ProvvigioneAccrual
{
  private const LIQUIDATION_DAYS = [
        'SOLUTION_ENEL_GETTONE' => 40,
        'GFB_FASTWEB_POD' => 60,
        'GFB_VODAFONE_COEFF' => 0,
        'GFB_RS_BIMESTRE' => 0,
        'ARIEL_2026' => 0,
        'ARQUATI_PNC' => 0,
        'GENERICO' => 0,
    ];

    public function resolveRegimeFromCategory(?object $category): string
    {
        if (!$category) {
            return 'GENERICO';
        }

        $regime = $category->get('regimeProvvigione');

        if ($regime) {
            return (string) $regime;
        }

        $gruppo = $category->get('gruppoProvvigione');

        $name = strtoupper(trim((string) $category->get('name')));

        if (str_contains($name, 'VODAFONE')) {
            return 'GFB_VODAFONE_COEFF';
        }

        if (str_contains($name, 'FASTWEB')) {
            return 'GFB_FASTWEB_POD';
        }

        if (str_contains($name, 'ENEL')) {
            return 'SOLUTION_ENEL_GETTONE';
        }

        return match ($gruppo) {
            'Tende da Sole', 'Pergole', 'Vetrate', 'Clima e altro' => 'ARQUATI_PNC',
            default => 'GENERICO',
        };
    }

    /**
     * GDL + brand Ariel → regime mail febbraio 2026 (priorità su gruppo ARQUATI).
     */
    public function resolveRegimeFromCommercial(
        ?object $category,
        ?string $fornitorePartnerName,
        ?string $productBrandName
    ): string {
        $brand = strtoupper(trim((string) $productBrandName));
        $partner = strtoupper(trim((string) $fornitorePartnerName));

        if (str_contains($brand, 'ARIEL') || str_contains($partner, 'GDL')) {
            return 'ARIEL_2026';
        }

        if ($category && $category->get('regimeProvvigione') === 'ARIEL_2026') {
            return 'ARIEL_2026';
        }

        return $this->resolveRegimeFromCategory($category);
    }

    public function resolveEventDate(?string $dataAttivazione, ?string $dataInstallazione): ?string
    {
        if ($dataAttivazione) {
            return $dataAttivazione;
        }

        return $dataInstallazione;
    }

    public function resolveCompetenceMonthStart(?string $eventDate): ?string
    {
        if (!$eventDate) {
            return null;
        }

        $dt = new DateTimeImmutable($eventDate);

        return $dt->format('Y-m-01');
    }

    public function calculateLiquidationDate(
        string $regime,
        ?string $dataAttivazione,
        ?string $dataInstallazione
    ): ?string {
        $days = self::LIQUIDATION_DAYS[$regime] ?? 0;

        if ($days <= 0) {
            return null;
        }

        $eventDate = $this->resolveEventDate($dataAttivazione, $dataInstallazione);

        if (!$eventDate) {
            return null;
        }

        $dt = new DateTimeImmutable($eventDate);
        $endOfMonth = $dt->modify('last day of this month');

        return $endOfMonth->modify('+' . $days . ' days')->format('Y-m-d');
    }

    public function getLiquidationDays(string $regime): int
    {
        return self::LIQUIDATION_DAYS[$regime] ?? 0;
    }

    /**
     * Stima provvigionale semplificata per forecast (da affinare per regime).
     */
    public function estimateForecastAmount(
        string $regime,
        ?float $imponibile
    ): ?float {
        if ($imponibile === null || $imponibile <= 0) {
            return null;
        }

        return match ($regime) {
            'ARIEL_2026' => round($imponibile * 0.15, 2),
            'GFB_VODAFONE_COEFF' => round($imponibile * 2.0, 2),
            default => null,
        };
    }
}
