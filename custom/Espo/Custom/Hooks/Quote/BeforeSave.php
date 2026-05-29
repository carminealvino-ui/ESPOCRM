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

    // =========================
    // DOPO IL SALVATAGGIO
    // =========================
    public function afterSave(Entity $entity, array $options)
    {
        $em = $this->getEntityManager();

        $provvigioniList = $em->getRepository('Provvigione')
            ->where(['contrattoId' => $entity->getId()])
            ->find();

        $totale = 0;

        foreach ($provvigioniList as $p) {

            $tipo = $p->get('tipo');
            $tasso = (float) $p->get('tassoProvvigioni');

            $base = 0;

            // =========================
            // BASE CALCOLO CORRETTA
            // =========================
            if ($tipo === 'Provvigione Base') {

                $amount = (float) $entity->get('amount');      // totale
                $tax = (float) $entity->get('taxAmount');      // IVA

                $base = $amount - $tax; // 👉 NETTO
            }

            elseif ($tipo === 'Plus Provvigionale' || $tipo === 'Minus Provvigionale') {
                $base = (float) $entity->get('minusPlus');
            }

            elseif ($tipo === 'Bonus (Sabato-Domenica)') {

                $date = $entity->get('dateQuoted');

                if ($date) {
                    $day = date('N', strtotime($date));

                    if ($day >= 6) {
                        $base = (float) ($entity->get('amount') - $entity->get('taxAmount'));
                    }
                }
            }

            // =========================
            // CALCOLO
            // =========================
            $importo = 0;

            if ($base > 0 && $tasso > 0) {
                $importo = ($base * $tasso) / 100;
            }

            // aggiorna senza loop
            $p->set('importo', $importo);
            $em->saveEntity($p, ['skipHooks' => true]);

            $totale += $importo;
        }

        // totale provvigioni
        $entity->set('totaleProvvigioni', $totale);
    }
}