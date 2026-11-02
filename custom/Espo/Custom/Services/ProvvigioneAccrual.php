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
    public const ARIEL_2026_CUTOVER = '2026-02-23';

    private const LIQUIDATION_DAYS = [
        'ENEL 2024' => 40,
        'FASTWEB ENERGIA' => 60,
        'VODAFONE - INFLOW (Coefficiente)' => 0,
        'VODAFONE - Revenue Sharing' => 0,
        'ARIEL 2026' => 0,
        'ARIEL 2025' => 0,
        'ARQUATI 2023' => 0,
        'GENERICO' => 0,
        'ARTEL' => 0,
        'PROGETTO' => 0,
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
            'Tende da Sole', 'Pergole', 'Vetrate', 'Clima e altro' => 'ARQUATI 2023',
            default => 'GENERICO',
        };
    }

    /**
     * GDL + brand Ariel → regime in base alla data commerciale.
     * Scalette minus (ARIEL_LEGACY) se data < 23/02/2026; altrimenti ARIEL_2026.
     */
    public function resolveRegimeFromCommercial(
        ?object $category,
        ?string $fornitorePartnerName,
        ?string $productBrandName,
        ?string $referenceDate = null
    ): string {
        $brand = strtoupper(trim((string) $productBrandName));
        $partner = strtoupper(trim((string) $fornitorePartnerName));

        if ($brand === 'ARTEL' || $partner === 'AMG') return 'ARTEL';
        if ($brand === 'PROGETTO' || $partner === 'PROGETTO') return 'PROGETTO';

        if (str_contains($brand, 'ARIEL') || str_contains($partner, 'GDL')) {
            return $this->resolveArielRegimeByDate($referenceDate);
        }

        if ($partner === 'ARQUATI' || $brand === 'ARQUATI') return 'ARQUATI 2023';

        if ($category && $category->get('regimeProvvigione') === 'ARIEL 2026') {
            return 'ARIEL 2026';
        }

        if ($category && $category->get('regimeProvvigione') === 'ARIEL 2025') {
            return 'ARIEL 2025';
        }

        return $this->resolveRegimeFromCategory($category);
    }

    public function resolveArielRegimeByDate(?string $referenceDate): string
    {
        if (!$referenceDate) {
            return 'ARIEL 2026';
        }

        try {
            $date = new DateTimeImmutable(substr($referenceDate, 0, 10));
        } catch (\Exception) {
            return 'ARIEL 2026';
        }

        $cutover = new DateTimeImmutable(self::ARIEL_2026_CUTOVER);

        return $date < $cutover ? 'ARIEL 2025' : 'ARIEL 2026';
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
            'ARIEL 2026', 'ARIEL 2025' => round($imponibile * 0.15, 2),
            'VODAFONE - INFLOW (Coefficiente)' => round($imponibile * 2.0, 2),
            default => null,
        };
    }
}
