<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\ORM\Entity;
use Espo\Core\Hooks\Base;

class BeforeSave extends Base
{
    // =========================
    // PRIMA DEL SALVATAGGIO
    // =========================
    public function beforeSave(Entity $entity, array $options)
    {
        $em = $this->getEntityManager();

        $itemList = $entity->get('itemList');

        if (empty($itemList) || !is_array($itemList)) {
            return;
        }

        $totalePrezzoCodice = 0;

        // =========================
        // ARTICOLI
        // =========================
        foreach ($itemList as $index => $item) {

            $productId = null;

            if (is_object($item) && isset($item->productId)) {
                $productId = $item->productId;
            } elseif (is_array($item) && isset($item['productId'])) {
                $productId = $item['productId'];
            }

            if ($productId) {

                $product = $em->getRepository('Product')
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