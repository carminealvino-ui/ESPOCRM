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
        // prezzo codice, minus/plus: Quote\SyncMinusPlus + QuotePricingCalculator
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