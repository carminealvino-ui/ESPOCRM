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

    /**
     * Aggiorna totaleProvvigioni sul contratto (anche save silent da subpanel).
     */
    public function afterSave(Entity $entity, array $options): void
    {
        if (!empty($options['skipHooks'])) {
            return;
        }

        $quoteId = $entity->get('contrattoId');

        if (!$quoteId) {
            return;
        }

        $em = $this->getEntityManager();
        $totale = 0.0;

        foreach ($em->getRepository('Provvigione')->where(['contrattoId' => $quoteId])->find() as $row) {
            $totale += (float) ($row->get('importo') ?? 0);
        }

        $totale = round($totale, 2);
        $quote = $em->getEntityById('Quote', $quoteId);

        if (!$quote) {
            return;
        }

        if (abs((float) ($quote->get('totaleProvvigioni') ?? 0) - $totale) < 0.001) {
            return;
        }

        $quote->set('totaleProvvigioni', $totale);
        $em->saveEntity($quote, ['skipHooks' => true, 'silent' => true]);
    }

    public function afterRemove(Entity $entity, array $options): void
    {
        $this->afterSave($entity, $options);
    }
}