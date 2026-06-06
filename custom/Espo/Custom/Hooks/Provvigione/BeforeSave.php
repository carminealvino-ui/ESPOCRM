<?php

namespace Espo\Custom\Hooks\Provvigione;

use Espo\Core\Hooks\Base;
use Espo\ORM\Entity;

/**
 * Provvigione manuale: importo = tassoProvvigioni % × importoContratto.
 * Nessuna regola provvigionale, nessuna logica per tipo.
 */
class BeforeSave extends Base
{
    public function beforeSave(Entity $entity, array $options): void
    {
        $quoteId = $entity->get('contrattoId');

        if (!$quoteId) {
            return;
        }

        $quote = $this->getEntityManager()->getEntityById('Quote', $quoteId);

        if (!$quote) {
            return;
        }

        $this->syncFromQuote($entity, $quote);
        $this->applyImportoFromTassoAndBase($entity, $quote);
        $entity->set('name', $this->buildName($entity, $quote));
    }

    private function syncFromQuote(Entity $entity, Entity $quote): void
    {
        $quoteName = $quote->get('name');

        if (is_string($quoteName) && $quoteName !== '') {
            $entity->set('contrattoName', $quoteName);
        }

        $accountId = $quote->get('accountId');

        if ($accountId) {
            $entity->set('clienteId', $accountId);

            $accountName = $quote->get('accountName');

            if (is_string($accountName) && $accountName !== '') {
                $entity->set('clienteName', $accountName);
            }
        }

        if (!$entity->hasAttribute('importoContratto')) {
            return;
        }

        $current = $this->floatOrNull($entity->get('importoContratto'));

        if ($current !== null && $current > 0) {
            return;
        }

        $importoContratto = $this->resolveQuoteImportoContratto($quote);

        if ($importoContratto !== null) {
            $entity->set('importoContratto', $importoContratto);
        }
    }

    private function applyImportoFromTassoAndBase(Entity $entity, Entity $quote): void
    {
        $tasso = $this->floatOrNull($entity->get('tassoProvvigioni'));

        if ($tasso === null || $tasso <= 0) {
            return;
        }

        $base = null;

        if ($entity->hasAttribute('importoContratto')) {
            $base = $this->floatOrNull($entity->get('importoContratto'));
        }

        if ($base === null || $base <= 0) {
            $base = $this->resolveQuoteImportoContratto($quote);
        }

        if ($base === null || $base <= 0) {
            return;
        }

        $entity->set('importo', round($base * $tasso / 100, 2));
    }

    private function buildName(Entity $entity, Entity $quote): string
    {
        $clientLabel = trim((string) ($quote->get('billingContactName') ?: $quote->get('accountName') ?: ''));
        $tipo = $entity->get('tipo');
        $importo = $this->floatOrNull($entity->get('importo')) ?? 0.0;
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

    private function resolveQuoteImportoContratto(Entity $quote): ?float
    {
        foreach (['importoContratto', 'amount', 'grandTotalAmount'] as $field) {
            $value = $this->floatOrNull($quote->get($field));

            if ($value !== null && $value > 0) {
                return round($value, 2);
            }
        }

        return null;
    }

    private function floatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
