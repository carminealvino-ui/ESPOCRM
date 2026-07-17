<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\BeforeSave as BeforeSaveHook;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Solo sync prezzo codice sugli articoli.
 * Il totale provvigioni NON si ricalcola qui (vedi AfterSaveTotaleProvvigioni).
 */
class BeforeSave implements BeforeSaveHook
{
    public static int $order = 5;

    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        $itemList = $entity->get('itemList');

        if (empty($itemList) || !is_array($itemList)) {
            return;
        }

        $totalePrezzoCodice = 0;

        foreach ($itemList as $item) {
            $productId = null;

            if (is_object($item) && isset($item->productId)) {
                $productId = $item->productId;
            } elseif (is_array($item) && isset($item['productId'])) {
                $productId = $item['productId'];
            }

            if ($productId) {
                $product = $this->entityManager
                    ->getRDBRepository('Product')
                    ->where(['id' => $productId])
                    ->findOne();

                if ($product) {
                    $prezzoCodice = $product->get('prezzoCodice');

                    if ($prezzoCodice === null) {
                        $prezzoCodice = $product->get('unitPrice');
                    }

                    if (is_object($item)) {
                        $item->prezzoCodice = $prezzoCodice;
                    } else {
                        $item['prezzoCodice'] = $prezzoCodice;
                    }
                }
            }

            $prezzo = is_object($item)
                ? ($item->prezzoCodice ?? 0)
                : ($item['prezzoCodice'] ?? 0);

            $qty = is_object($item)
                ? ($item->quantity ?? 0)
                : ($item['quantity'] ?? 0);

            if ($prezzo && $qty) {
                $totalePrezzoCodice += ($prezzo * $qty);
            }
        }

        $entity->set('itemList', $itemList);
        $entity->set('totalPrezzoCodice', $totalePrezzoCodice);
    }
}
