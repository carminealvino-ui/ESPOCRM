<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Aliquota IVA (%) da profilo fiscale (Tax) o codice imposta (TaxCode) su contratto/ordine.
 */
class FiscalProfileRateResolver
{
    public const DEFAULT_ALIQUOTA_IVA = 10.0;

    public function __construct(
        private EntityManager $entityManager
    ) {}

    /**
     * Contratto (Quote): profilo fiscale e codice imposta hanno priorità sul campo aliquotaIVA.
     */
    public function resolveForQuote(Entity $quote): ?float
    {
        if ($quote->getEntityType() !== 'Quote') {
            return null;
        }

        $fromProfile = $this->resolveFromTaxProfile($quote);

        if ($fromProfile !== null) {
            return $fromProfile;
        }

        return $this->resolveFromTaxCodeId($quote->get('taxCodeId'));
    }

    public function resolveFromTaxProfile(Entity $entity): ?float
    {
        $taxId = $entity->get('taxId');

        if (!$taxId) {
            return null;
        }

        $tax = $this->entityManager->getEntityById('Tax', $taxId);

        if (!$tax || $tax->get('status') === 'Inactive') {
            return null;
        }

        $basis = (string) ($tax->get('basis') ?? '');

        if ($basis === 'Rate') {
            $rate = self::parsePercentRate($tax->get('rate'));

            if ($rate !== null) {
                return $rate;
            }
        }

        $fromLinkedCode = $this->resolveFromTaxCodeId($tax->get('taxCodeId'));

        if ($fromLinkedCode !== null) {
            return $fromLinkedCode;
        }

        return self::parsePercentRate($tax->get('rate'));
    }

    public function resolveFromTaxCodeId(?string $taxCodeId): ?float
    {
        if (!$taxCodeId) {
            return null;
        }

        $taxCode = $this->entityManager->getEntityById('TaxCode', $taxCodeId);

        if (!$taxCode || $taxCode->get('status') === 'Inactive') {
            return null;
        }

        if ((string) ($taxCode->get('type') ?? '') !== 'Percentage') {
            return null;
        }

        return self::parsePercentRate($taxCode->get('rate'));
    }

    /**
     * @param mixed $value Tasso percentuale (10, 22) o frazione (0.10)
     */
    public static function parsePercentRate(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        $f = (float) $value;

        if ($f <= 0) {
            return null;
        }

        if ($f > 0 && $f < 1) {
            return round($f * 100, 3);
        }

        return round($f, 3);
    }
}
