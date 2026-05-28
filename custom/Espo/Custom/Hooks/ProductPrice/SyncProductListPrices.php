<?php

namespace Espo\Custom\Hooks\ProductPrice;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Allinea Product.listPrice (IVA escl.) con ProductPrice sul listino prezzi.
 *
 * Su listino ARIEL con is_tax_inclusive=1:
 * - tab Prezzi → price = 5200 IVI
 * - scheda prodotto listPrice = 4727,27 netti (provvigioni / margini)
 */
class SyncProductListPrices implements AfterSave
{
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

        if (!$productId || !$priceBookId || $price === null || $price === '') {
            return;
        }

        $product = $this->entityManager->getEntityById('Product', $productId);
        $priceBook = $this->entityManager->getEntityById('PriceBook', $priceBookId);

        if (!$product || !$priceBook) {
            return;
        }

        $amount = (float) $price;
        $aliquota = 10.0;
        $taxInclusive = (bool) $priceBook->get('isTaxInclusive');

        $patch = [];

        if ($taxInclusive) {
            $net = round($amount / (1 + $aliquota / 100), 2);
            $patch['prezzoListinoIvaInclusa'] = round($amount, 2);
            $patch['listPrice'] = $net;
            $patch['unitPrice'] = $net;
        } else {
            $patch['listPrice'] = round($amount, 2);
            $patch['unitPrice'] = round($amount, 2);
            $patch['prezzoListinoIvaInclusa'] = round($amount * (1 + $aliquota / 100), 2);
        }

        $product->set($patch);
        $this->entityManager->saveEntity($product, [
            'skipHooks' => true,
            'silent' => true,
        ]);
    }
}
