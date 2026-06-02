<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Sincronizza coppie prezzo IVA inclusa / esclusa (listino e codice) su ProductPrice e Product.
 */
class IvaDualPriceSync
{
    public const DEFAULT_ALIQUOTA_IVA = 10.0;

    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function syncProductPriceOnBeforeSave(Entity $entity): void
    {
        if ($entity->getEntityType() !== 'ProductPrice') {
            return;
        }

        $aliquota = $this->resolveAliquotaForProductPrice($entity);
        $entity->set('aliquotaIva', $aliquota);

        $taxInclusive = $this->isPriceBookTaxInclusive($entity);

        $this->syncListinoFields($entity, $aliquota, $taxInclusive);
        $this->syncCodiceFields($entity, $aliquota);
        $this->syncNativePriceField($entity, $taxInclusive);
    }

    public function syncProductFromProductPrice(Entity $productPrice): void
    {
        $productId = $productPrice->get('productId');

        if (!$productId) {
            return;
        }

        $product = $this->entityManager->getEntityById('Product', $productId);

        if (!$product) {
            return;
        }

        $patch = [];

        $listinoNet = $this->floatOrNull($productPrice->get('prezzoListinoIvaEsclusa'));
        $listinoIvi = $this->floatOrNull($productPrice->get('prezzoListinoIvaInclusa'));

        if ($listinoNet !== null && $listinoNet > 0) {
            if ($product->hasAttribute('listPrice')) {
                $patch['listPrice'] = round($listinoNet, 2);
            }

            if ($product->hasAttribute('unitPrice')) {
                $patch['unitPrice'] = round($listinoNet, 2);
            }
        }

        if ($listinoIvi !== null && $listinoIvi > 0 && $product->hasAttribute('prezzoListinoIvaInclusa')) {
            $patch['prezzoListinoIvaInclusa'] = round($listinoIvi, 2);
        }

        $codiceNet = $this->floatOrNull($productPrice->get('prezzoCodice'));
        $codiceIvi = $this->floatOrNull($productPrice->get('prezzoCodiceIvaInclusa'));

        if ($codiceNet !== null && $codiceNet > 0 && $product->hasAttribute('prezzoCodice')) {
            $patch['prezzoCodice'] = round($codiceNet, 2);
        }

        if ($codiceIvi !== null && $codiceIvi > 0 && $product->hasAttribute('prezzoCodiceIvaInclusa')) {
            $patch['prezzoCodiceIvaInclusa'] = round($codiceIvi, 2);
        }

        if ($patch === []) {
            return;
        }

        $changed = false;

        foreach ($patch as $field => $value) {
            $current = $this->floatOrNull($product->get($field));

            if ($current === null || abs($current - $value) > 0.009) {
                $product->set($field, $value);
                $changed = true;
            }
        }

        if ($changed) {
            $this->entityManager->saveEntity($product, ['silent' => true]);
        }
    }

    private function syncListinoFields(Entity $entity, float $aliquota, bool $taxInclusive): void
    {
        $iviField = 'prezzoListinoIvaInclusa';
        $netField = 'prezzoListinoIvaEsclusa';

        if ($entity->isAttributeChanged('price') && !$this->pairFieldChanged($entity, $iviField, $netField)) {
            $price = $this->floatOrNull($entity->get('price'));

            if ($price !== null && $price > 0) {
                if ($taxInclusive) {
                    $entity->set($iviField, round($price, 2));
                    $entity->set($netField, self::toEsclusa($price, $aliquota));
                } else {
                    $entity->set($netField, round($price, 2));
                    $entity->set($iviField, self::toInclusa($price, $aliquota));
                }

                return;
            }
        }

        $this->syncIvaPair($entity, $iviField, $netField, $aliquota);
    }

    private function syncCodiceFields(Entity $entity, float $aliquota): void
    {
        $this->syncIvaPair($entity, 'prezzoCodiceIvaInclusa', 'prezzoCodice', $aliquota);
    }

    private function syncIvaPair(Entity $entity, string $iviField, string $netField, float $aliquota): void
    {
        $ivi = $this->floatOrNull($entity->get($iviField));
        $net = $this->floatOrNull($entity->get($netField));
        $iviChanged = $entity->isAttributeChanged($iviField);
        $netChanged = $entity->isAttributeChanged($netField);

        if ($iviChanged && !$netChanged && $ivi !== null && $ivi > 0) {
            $entity->set($netField, self::toEsclusa($ivi, $aliquota));

            return;
        }

        if ($netChanged && !$iviChanged && $net !== null && $net > 0) {
            $entity->set($iviField, self::toInclusa($net, $aliquota));

            return;
        }

        if ($iviChanged && $netChanged) {
            if ($ivi !== null && $ivi > 0) {
                $entity->set($netField, self::toEsclusa($ivi, $aliquota));
            } elseif ($net !== null && $net > 0) {
                $entity->set($iviField, self::toInclusa($net, $aliquota));
            }

            return;
        }

        if ($ivi !== null && $ivi > 0 && ($net === null || $net <= 0)) {
            $entity->set($netField, self::toEsclusa($ivi, $aliquota));
        } elseif ($net !== null && $net > 0 && ($ivi === null || $ivi <= 0)) {
            $entity->set($iviField, self::toInclusa($net, $aliquota));
        }
    }

    private function syncNativePriceField(Entity $entity, bool $taxInclusive): void
    {
        $ivi = $this->floatOrNull($entity->get('prezzoListinoIvaInclusa'));
        $net = $this->floatOrNull($entity->get('prezzoListinoIvaEsclusa'));

        if ($taxInclusive && $ivi !== null && $ivi > 0) {
            $entity->set('price', round($ivi, 2));

            return;
        }

        if (!$taxInclusive && $net !== null && $net > 0) {
            $entity->set('price', round($net, 2));

            return;
        }

        if ($ivi !== null && $ivi > 0) {
            $entity->set('price', round($ivi, 2));

            return;
        }

        if ($net !== null && $net > 0) {
            $entity->set('price', round($net, 2));
        }
    }

    private function pairFieldChanged(Entity $entity, string $iviField, string $netField): bool
    {
        return $entity->isAttributeChanged($iviField) || $entity->isAttributeChanged($netField);
    }

    private function resolveAliquotaForProductPrice(Entity $entity): float
    {
        $aliquota = $this->floatOrNull($entity->get('aliquotaIva'));

        if ($aliquota !== null && $aliquota > 0) {
            return $aliquota;
        }

        return self::DEFAULT_ALIQUOTA_IVA;
    }

    private function isPriceBookTaxInclusive(Entity $entity): bool
    {
        $priceBookId = $entity->get('priceBookId');

        if (!$priceBookId) {
            return false;
        }

        $priceBook = $this->entityManager->getEntityById('PriceBook', $priceBookId);

        return $priceBook && (bool) $priceBook->get('isTaxInclusive');
    }

    public static function toEsclusa(float $importoIvi, float $aliquotaPercent): float
    {
        if ($aliquotaPercent <= 0) {
            return round($importoIvi, 2);
        }

        return round($importoIvi / (1 + $aliquotaPercent / 100), 2);
    }

    public static function toInclusa(float $importoNetto, float $aliquotaPercent): float
    {
        if ($aliquotaPercent <= 0) {
            return round($importoNetto, 2);
        }

        return round($importoNetto * (1 + $aliquotaPercent / 100), 2);
    }

    private function floatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
