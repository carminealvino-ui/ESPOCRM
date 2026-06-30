<?php

namespace Espo\Custom\Services;

use Espo\Core\ORM\EntityManager;
use Espo\ORM\Entity;

/**
 * Palette e regole colore calendario Appuntamento (accessibile daltonismo).
 *
 * - Planned → colore del ProductBrand (fallback blu)
 * - Held (svolto) → verde/teal ad alto contrasto
 * - Held + Chiuso Positivamente → arancio
 * - Not Held / Ingestibile → vermiglio (distinto dal verde)
 *
 * Palette ispirata a Okabe–Ito ( distinguibile con protan/deuteranopia ).
 */
class AppuntamentoCalendarColor
{
    /** Verde/teal “svolto” — non confondibile col vermiglio annullato. */
    public const COLOR_HELD = '#009E73';

    /** Arancio — chiuso positivamente. */
    public const COLOR_CHIUSO_POSITIVAMENTE = '#E69F00';

    /** Vermiglio — annullato / ingestibile. */
    public const COLOR_ANNULLATO_INGESTIBILE = '#D55E00';

    /** Fallback appuntamento pianificato senza brand. */
    public const COLOR_PLANNED_FALLBACK = '#0173B2';

    /**
     * Colori brand consigliati (solo appuntamenti Planned).
     *
     * @var array<string, string>
     */
    public const BRAND_PALETTE_DALTON = [
        'ARIEL' => '#0173B2',
        'ARQUATI' => '#785EF0',
        'PROGETTO' => '#2EC4B6',
        'ARTEL' => '#56B4E9',
        'GFB' => '#332288',
    ];

    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function resolveColor(Entity $entity): ?string
    {
        $status = (string) ($entity->get('status') ?: '');
        $sottostato = (string) ($entity->get('sottostato') ?: '');

        if ($status === 'Planned') {
            return $this->resolveBrandColor($entity) ?? self::COLOR_PLANNED_FALLBACK;
        }

        if ($status === 'Held' && $sottostato === 'Chiuso Positivamente') {
            return self::COLOR_CHIUSO_POSITIVAMENTE;
        }

        if ($status === 'Held') {
            return self::COLOR_HELD;
        }

        if ($status === 'Not Held' || $status === 'Ingestibile') {
            return self::COLOR_ANNULLATO_INGESTIBILE;
        }

        return null;
    }

    public function resolveBrandColor(Entity $entity): ?string
    {
        $brandId = $entity->get('productBrandId');

        if (!$brandId) {
            return null;
        }

        $brand = $this->entityManager->getEntityById('ProductBrand', $brandId);

        if (!$brand) {
            return null;
        }

        $color = trim((string) ($brand->get('color') ?: ''));

        return $color !== '' ? $color : null;
    }

    public function resolveDisponibilitaColor(Entity $entity): ?string
    {
        $brandName = $this->resolveDisponibilitaBrandName($entity);

        if ($brandName === '') {
            return null;
        }

        $brand = $this->entityManager
            ->getRDBRepository('ProductBrand')
            ->where(['name' => $brandName])
            ->findOne();

        if ($brand) {
            $color = trim((string) ($brand->get('color') ?: ''));

            if ($color !== '') {
                return $color;
            }
        }

        $key = strtoupper($brandName);

        $jsonColor = self::loadJsonBrandColor($key);

        if ($jsonColor !== null) {
            return $jsonColor;
        }

        return self::BRAND_PALETTE_DALTON[$key] ?? null;
    }

    private static function loadJsonBrandColor(string $brandKey): ?string
    {
        static $map = null;

        if ($map === null) {
            $map = [];
            $candidates = [
                getcwd() . '/tools/data/brand-calendar-colors.json',
                dirname(__DIR__, 4) . '/tools/data/brand-calendar-colors.json',
            ];

            foreach ($candidates as $path) {
                if (!is_readable($path)) {
                    continue;
                }

                $decoded = json_decode((string) file_get_contents($path), true);

                if (!is_array($decoded)) {
                    continue;
                }

                foreach ($decoded as $name => $hex) {
                    $map[strtoupper((string) $name)] = trim((string) $hex);
                }

                break;
            }
        }

        $color = $map[$brandKey] ?? null;

        return ($color !== null && $color !== '') ? $color : null;

    private function resolveDisponibilitaBrandName(Entity $entity): string
    {
        $brandId = $entity->get('productBrandId');

        if ($brandId) {
            $brand = $this->entityManager->getEntityById('ProductBrand', $brandId);

            if ($brand) {
                return trim((string) $brand->get('name'));
            }
        }

        $azienda = trim((string) ($entity->get('azienda') ?: ''));

        if ($azienda !== '') {
            return $azienda;
        }

        $name = trim((string) ($entity->get('name') ?: ''));

        if ($name !== '' && preg_match('/^([^|]+)/u', $name, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

    /**
     * @return array<string, string>
     */
    public static function defaultBrandColorMap(): array
    {
        return self::BRAND_PALETTE_DALTON;
    }
}
