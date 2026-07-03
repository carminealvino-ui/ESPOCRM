<?php

namespace Espo\Custom\Services;

use Espo\Core\Utils\Config;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Somma importi Provvigione collegate al contratto e aggiorna totaleProvvigioni.
 */
class QuoteProvvigioniSync
{
    public function __construct(
        private EntityManager $entityManager,
        private Config $config
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

        $currency = $this->resolveQuoteCurrency($quote);
        $current = (float) ($quote->get('totaleProvvigioni') ?? 0);
        $currentCurrency = $quote->get('totaleProvvigioniCurrency');
        $currentConverted = $quote->get('totaleProvvigioniConverted');

        $needsSave = abs($current - $totale) >= 0.001
            || $currentCurrency !== $currency
            || ($totale > 0 && ($currentConverted === null || $currentConverted === ''));

        if (!$needsSave) {
            return $totale;
        }

        $quote->set([
            'totaleProvvigioni' => $totale,
            'totaleProvvigioniCurrency' => $currency,
        ]);

        $this->entityManager->saveEntity($quote, [
            'silent' => true,
            'skipProvvigioniQuoteSync' => true,
        ]);

        return $totale;
    }

    private function resolveQuoteCurrency(Entity $quote): string
    {
        foreach (['importoContrattoCurrency', 'amountCurrency', 'grandTotalAmountCurrency'] as $field) {
            $currency = $quote->get($field);

            if (is_string($currency) && $currency !== '') {
                return $currency;
            }
        }

        $default = $this->config->get('defaultCurrency');

        if (is_string($default) && $default !== '') {
            return $default;
        }

        return 'EUR';
    }
}
