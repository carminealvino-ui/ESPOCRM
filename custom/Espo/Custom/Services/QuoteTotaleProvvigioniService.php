<?php

namespace Espo\Custom\Services;

use Espo\ORM\EntityManager;

/**
 * Aggiorna totaleProvvigioni sul contratto dalla somma delle righe Provvigione.
 */
class QuoteTotaleProvvigioniService
{
    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function syncForQuoteId(string $quoteId): void
    {
        $provvigioni = $this->entityManager
            ->getRDBRepository('Provvigione')
            ->where(['contrattoId' => $quoteId])
            ->find();

        $totale = 0.0;

        foreach ($provvigioni as $provvigione) {
            $totale += (float) ($provvigione->get('importo') ?? 0);
        }

        $quote = $this->entityManager->getEntityById('Quote', $quoteId);

        if (!$quote) {
            return;
        }

        if ((float) ($quote->get('totaleProvvigioni') ?? 0) === $totale) {
            return;
        }

        // Update diretto: evita conflitto version (optimisticConcurrencyControl).
        $this->entityManager
            ->getQuery()
            ->update()
            ->in('Quote')
            ->set(['totaleProvvigioni' => $totale])
            ->where(['id' => $quoteId])
            ->execute();
    }
}
