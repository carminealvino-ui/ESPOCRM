<?php

namespace Espo\Custom\Services;

use Espo\ORM\EntityManager;

/**
 * Somma importi Provvigione collegate al contratto e aggiorna totaleProvvigioni.
 */
class QuoteProvvigioniSync
{
    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function sumTotaleProvvigioni(string $quoteId): float
    {
        $totale = 0.0;

        $collection = $this->entityManager
            ->getRDBRepository('Provvigione')
            ->where(['contrattoId' => $quoteId])
            ->find();

        foreach ($collection as $provvigione) {
            $totale += (float) ($provvigione->get('importo') ?? 0);
        }

        return round($totale, 2);
    }

    public function syncTotaleProvvigioniOnQuote(string $quoteId): float
    {
        $totale = $this->sumTotaleProvvigioni($quoteId);

        $quote = $this->entityManager->getEntityById('Quote', $quoteId);

        if (!$quote) {
            return $totale;
        }

        $current = (float) ($quote->get('totaleProvvigioni') ?? 0);

        if (abs($current - $totale) < 0.001) {
            return $totale;
        }

        $quote->set('totaleProvvigioni', $totale);

        $this->entityManager->saveEntity($quote, [
            'skipHooks' => true,
            'silent' => true,
        ]);

        return $totale;
    }
}
