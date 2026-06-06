<?php

namespace Espo\Custom\Hooks\Provvigione;

use Espo\Core\Hooks\Base;
use Espo\ORM\Entity;

class BeforeSave extends Base
{
    public function beforeSave(Entity $entity, array $options): void
    {
        $quoteId = $entity->get('contrattoId');

        if (!$quoteId) {
            return;
        }

        $em = $this->getEntityManager();
        $quote = $em->getEntityById('Quote', $quoteId);

        if (!$quote) {
            return;
        }

        $this->syncRelatedFieldsFromQuote($entity, $quote);

        $tipo = $entity->get('tipo');
        $tasso = (float) $entity->get('tassoProvvigioni');
        $base = $this->resolveImportoBase($quote, $tipo);
        $importo = 0.0;

        if ($base > 0 && $tasso > 0) {
            $importo = round(($base * $tasso) / 100, 2);
        }

        $entity->set('importo', $importo);
        $entity->set('name', $this->buildProvvigioneName($quote, $tipo, $importo));
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

    private function syncRelatedFieldsFromQuote(Entity $entity, Entity $quote): void
    {
        $quoteName = $quote->get('name');

        if (is_string($quoteName) && $quoteName !== '') {
            $entity->set('contrattoName', $quoteName);
        }

        $accountId = $quote->get('accountId');

        if (!$accountId) {
            return;
        }

        $entity->set('clienteId', $accountId);

        $accountName = $quote->get('accountName');

        if (is_string($accountName) && $accountName !== '') {
            $entity->set('clienteName', $accountName);
        }
    }

    private function resolveImportoBase(Entity $quote, ?string $tipo): float
    {
        if ($tipo === null || $tipo === '') {
            return 0.0;
        }

        if ($tipo === 'Provvigione Base' || $tipo === 'Referenza Personale') {
            return $this->resolveQuoteImponibile($quote);
        }

        if ($tipo === 'Plus Provvigionale' || $tipo === 'Minus Provvigionale') {
            return (float) ($quote->get('minusPlus') ?? 0);
        }

        if ($tipo === 'Bonus (Sabato-Domenica)') {
            $date = $quote->get('dateQuoted');

            if ($date) {
                $day = (int) date('N', strtotime((string) $date));

                if ($day >= 6) {
                    return $this->resolveQuoteImponibile($quote);
                }
            }

            return 0.0;
        }

        if (strpos($tipo, 'Gara') !== false) {
            return $this->resolveGaraBase($quote, $tipo);
        }

        // Tipi aggiunti da Entity Manager: default = imponibile contratto
        return $this->resolveQuoteImponibile($quote);
    }

    private function resolveQuoteImponibile(Entity $quote): float
    {
        foreach (['amount', 'importoContratto', 'grandTotalAmount'] as $field) {
            $value = $quote->get($field);

            if ($value !== null && $value !== '' && is_numeric($value) && (float) $value > 0) {
                return (float) $value;
            }
        }

        return 0.0;
    }

    private function resolveGaraBase(Entity $quote, string $tipo): float
    {
        $amount = $this->resolveQuoteImponibile($quote);

        if ($amount <= 0) {
            return 0.0;
        }

        if (strpos($tipo, '2.5') !== false && $amount > 2500) {
            return $amount;
        }

        if (strpos($tipo, '3.5') !== false && $amount > 3500) {
            return $amount;
        }

        if (strpos($tipo, '5') !== false && $amount > 5000) {
            return $amount;
        }

        return 0.0;
    }

    private function buildProvvigioneName(Entity $quote, ?string $tipo, float $importo): string
    {
        $clientLabel = trim((string) ($quote->get('billingContactName') ?: $quote->get('accountName') ?: ''));
        $parts = [];

        if ($clientLabel !== '') {
            $parts[] = $clientLabel;
        }

        if (is_string($tipo) && $tipo !== '') {
            $parts[] = $tipo;
        }

        $parts[] = '€. ' . number_format($importo, 2, '.', '');

        return mb_strtoupper(implode(' - ', $parts));
    }
}
