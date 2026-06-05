<?php

namespace Espo\Custom\Hooks\Provvigione;

use Espo\ORM\Entity;
use Espo\Core\Hooks\Base;

class BeforeSave extends Base
{
    public function beforeSave(Entity $entity, array $options)
    {
        if (!$entity->get('contrattoId')) {
            return;
        }

        $em = $this->getEntityManager();

        $quote = $em->getRepository('Quote')
            ->where(['id' => $entity->get('contrattoId')])
            ->findOne();

        if (!$quote) {
            return;
        }

        $tipo = $entity->get('tipo');
        $tasso = (float) $entity->get('tassoProvvigioni');

        $base = 0;

        // BASE CALCOLO
        if ($tipo === 'Provvigione Base') {
            $base = (float) $quote->get('amount');
        }

        elseif ($tipo === 'Plus Provvigionale' || $tipo === 'Minus Provvigionale') {
            $base = (float) $quote->get('minusPlus');
        }

        elseif ($tipo === 'Bonus (Sabato-Domenica)') {

            $date = $quote->get('dateQuoted');

            if ($date) {
                $day = date('N', strtotime($date));

                if ($day >= 6) {
                    $base = (float) $quote->get('amount');
                }
            }
        }

        elseif (strpos($tipo, 'Gara') !== false) {

            $amount = (float) $quote->get('amount');

            if (strpos($tipo, '2.5') !== false && $amount > 2500) {
                $base = $amount;
            }

            if (strpos($tipo, '3.5') !== false && $amount > 3500) {
                $base = $amount;
            }

            if (strpos($tipo, '5') !== false && $amount > 5000) {
                $base = $amount;
            }
        }

        // CALCOLO
        $importo = 0;

        if ($base > 0 && $tasso > 0) {
            $importo = ($base * $tasso) / 100;
        }

        // SET DIRETTO → senza save
        $entity->set('importo', $importo);
    }
}