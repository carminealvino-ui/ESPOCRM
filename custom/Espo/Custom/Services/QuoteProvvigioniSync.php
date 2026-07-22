<?php

namespace Espo\Custom\Services;

use Espo\ORM\EntityManager;

/**
 * Somma Provvigioni del contratto → Quote.totaleProvvigioni.
 *
 * Usa importoConsolidato (fallback importo). Include anche Inesigibili.
 */
class QuoteProvvigioniSync
{
    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function sumTotaleProvvigioni(string $quoteId): ?float
    {
        $collection = $this->entityManager
            ->getRDBRepository('Provvigione')
            ->where(['contrattoId' => $quoteId])
            ->find();

        $totale = 0.0;
        $counted = 0;

        foreach ($collection as $provvigione) {
            $importo = $provvigione->get('importoConsolidato');

            if ($importo === null || $importo === '') {
                $importo = $provvigione->get('importo');
            }

            if ($importo === null || $importo === '') {
                continue;
            }

            $totale += (float) $importo;
            $counted++;
        }

        return $counted > 0 ? round($totale, 2) : null;
    }

    public function syncTotaleProvvigioniOnQuote(string $quoteId): ?float
    {
        $totale = $this->sumTotaleProvvigioni($quoteId);

        $quote = $this->entityManager->getEntityById('Quote', $quoteId);

        if (!$quote) {
            return $totale;
        }

        $current = $quote->get('totaleProvvigioni');
        $currentNorm = $current === null || $current === '' ? null : round((float) $current, 2);

        if ($currentNorm === $totale) {
            return $totale;
        }

        $currency = $quote->get('amountCurrency')
            ?: $quote->get('importoContrattoCurrency')
            ?: 'EUR';

        $quote->set([
            'totaleProvvigioni' => $totale,
            'totaleProvvigioniCurrency' => $currency,
        ]);

        $this->entityManager->saveEntity($quote, [
            'skipHooks' => true,
            'silent' => true,
            'skipFormula' => true,
        ]);

        return $totale;
    }
}
