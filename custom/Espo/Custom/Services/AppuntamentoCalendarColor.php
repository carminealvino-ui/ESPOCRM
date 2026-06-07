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
        'PROGETTO' => '#CC79A7',
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

    /**
     * @return array<string, string>
     */
    public static function defaultBrandColorMap(): array
    {
        return self::BRAND_PALETTE_DALTON;
    }
}
