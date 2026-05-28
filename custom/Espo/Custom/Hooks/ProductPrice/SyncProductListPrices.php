<?php

namespace Espo\Custom\Hooks\ProductPrice;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Allinea Product da riga listino (ProductPrice):
 * - price → listPrice netto + prezzoListinoIvaInclusa
 * - prezzoCodice / prezzoCodiceIvaInclusa → stessi campi sul prodotto
 */
class SyncProductListPrices implements AfterSave
{
    private const ALIQUOTA_IVA = 10.0;

    public static int $order = 10;

    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function afterSave(Entity $productPrice, SaveOptions $options): void
    {
        if ($options->get('silent') || $options->get('skipHooks')) {
            return;
        }

        if ($productPrice->get('status') !== 'Active') {
            return;
        }

        $productId = $productPrice->get('productId');
        $priceBookId = $productPrice->get('priceBookId');
        $price = $productPrice->get('price');

        if (!$productId || !$priceBookId) {
            return;
        }

        $product = $this->entityManager->getEntityById('Product', $productId);
        $priceBook = $this->entityManager->getEntityById('PriceBook', $priceBookId);

        if (!$product || !$priceBook) {
            return;
        }

        $taxInclusive = (bool) $priceBook->get('isTaxInclusive');
        $patch = [];

        if ($price !== null && $price !== '') {
            $amount = (float) $price;

            if ($taxInclusive) {
                $net = round($amount / (1 + self::ALIQUOTA_IVA / 100), 2);
                $patch['prezzoListinoIvaInclusa'] = round($amount, 2);
                $patch['listPrice'] = $net;
                $patch['unitPrice'] = $net;
            } else {
                $patch['listPrice'] = round($amount, 2);
                $patch['unitPrice'] = round($amount, 2);
                $patch['prezzoListinoIvaInclusa'] = round($amount * (1 + self::ALIQUOTA_IVA / 100), 2);
            }
        }

        $this->applyPrezzoCodicePatch($productPrice, $product, $taxInclusive, $patch);

        if ($patch === []) {
            return;
        }

        $product->set($patch);
        $this->entityManager->saveEntity($product, [
            'skipHooks' => true,
            'silent' => true,
        ]);
    }

    /**
     * @param array<string, float> $patch
     */
    private function applyPrezzoCodicePatch(
        Entity $productPrice,
        Entity $product,
        bool $taxInclusive,
        array &$patch
    ): void {
        $codiceIvi = $this->floatOrNull($productPrice->get('prezzoCodiceIvaInclusa'));
        $codiceNet = $this->floatOrNull($productPrice->get('prezzoCodice'));

        if ($codiceIvi === null && $codiceNet === null) {
            return;
        }

        if ($codiceIvi !== null && $codiceIvi > 0) {
            if ($product->hasAttribute('prezzoCodiceIvaInclusa')) {
                $patch['prezzoCodiceIvaInclusa'] = round($codiceIvi, 2);
            }

            if ($product->hasAttribute('prezzoCodice')) {
                $patch['prezzoCodice'] = $taxInclusive
                    ? round($codiceIvi / (1 + self::ALIQUOTA_IVA / 100), 2)
                    : round($codiceIvi, 2);
            }

            return;
        }

        if ($codiceNet !== null && $codiceNet > 0) {
            if ($product->hasAttribute('prezzoCodice')) {
                $patch['prezzoCodice'] = round($codiceNet, 2);
            }

            if ($product->hasAttribute('prezzoCodiceIvaInclusa')) {
                $patch['prezzoCodiceIvaInclusa'] = $taxInclusive
                    ? round($codiceNet * (1 + self::ALIQUOTA_IVA / 100), 2)
                    : round($codiceNet, 2);
            }
        }
    }

    private function floatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
