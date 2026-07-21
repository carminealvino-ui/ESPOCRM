<?php

namespace Espo\Custom\Services;

use DateTime;
use DateTimeImmutable;

/**
 * Date di competenza e liquidazione provvigionale in base al regime (brand/categoria).
 */
class ProvvigioneAccrual
{
    /** Contratti con data < cutover usano scalette minus (ARIEL_LEGACY). */
    public const ARIEL_2026_CUTOVER = '2026-01-23';

    private const LIQUIDATION_DAYS = [
        'SOLUTION_ENEL_GETTONE' => 40,
        'GFB_FASTWEB_POD' => 60,
        'GFB_VODAFONE_COEFF' => 0,
        'GFB_RS_BIMESTRE' => 0,
        'ARIEL_2026' => 0,
        'ARIEL_LEGACY' => 0,
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

        return match ($gruppo) {
            'Tende da Sole', 'Pergole', 'Vetrate', 'Clima e altro' => 'ARQUATI_PNC',
            default => 'GENERICO',
        };
    }

    /**
     * GDL + brand Ariel → regime in base alla data commerciale.
     * Scalette minus (ARIEL_LEGACY) se data < 23/01/2026; altrimenti ARIEL_2026.
     */
    public function resolveRegimeFromCommercial(
        ?object $category,
        ?string $fornitorePartnerName,
        ?string $productBrandName,
        ?string $referenceDate = null
    ): string {
        $brand = strtoupper(trim((string) $productBrandName));
        $partner = strtoupper(trim((string) $fornitorePartnerName));

        if (str_contains($brand, 'ARIEL') || str_contains($partner, 'GDL')) {
            return $this->resolveArielRegimeByDate($referenceDate);
        }

        if ($category && $category->get('regimeProvvigione') === 'ARIEL_2026') {
            return 'ARIEL_2026';
        }

        if ($category && $category->get('regimeProvvigione') === 'ARIEL_LEGACY') {
            return 'ARIEL_LEGACY';
        }

        return $this->resolveRegimeFromCategory($category);
    }

    public function resolveArielRegimeByDate(?string $referenceDate): string
    {
        if (!$referenceDate) {
            return 'ARIEL_2026';
        }

        try {
            $date = new DateTimeImmutable(substr($referenceDate, 0, 10));
        } catch (\Exception) {
            return 'ARIEL_2026';
        }

        $cutover = new DateTimeImmutable(self::ARIEL_2026_CUTOVER);

        return $date < $cutover ? 'ARIEL_LEGACY' : 'ARIEL_2026';
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
            'ARIEL_2026', 'ARIEL_LEGACY' => round($imponibile * 0.15, 2),
            'GFB_VODAFONE_COEFF' => round($imponibile * 2.0, 2),
            default => null,
        };
    }
}
