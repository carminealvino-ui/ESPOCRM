<?php

namespace Espo\Custom\Hooks\Provvigione;

use Espo\Core\Hooks\Base;
use Espo\ORM\Entity;

/**
 * Provvigione manuale: importo = tassoProvvigioni % × imponibile contratto.
 * Nessuna regola provvigionale. Hook Base (compatibilità Espo produzione).
 */
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

        $this->syncLinksFromQuote($entity, $quote);
        $this->applyImporto($entity, $quote);
        $entity->set('name', $this->buildName($entity, $quote));
    }

    private function syncLinksFromQuote(Entity $entity, Entity $quote): void
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

    private function applyImporto(Entity $entity, Entity $quote): void
    {
        $tasso = $this->toFloat($entity->get('tassoProvvigioni'));

        if ($tasso <= 0) {
            return;
        }

        $base = 0.0;

        if ($entity->hasAttribute('importoContratto')) {
            $base = $this->toFloat($entity->get('importoContratto'));
        }

        if ($base <= 0) {
            $base = $this->toFloat($quote->get('amount'));
        }

        if ($base <= 0) {
            $base = $this->toFloat($quote->get('importoContratto'));
        }

        if ($base <= 0) {
            return;
        }

        $entity->set('importo', round($base * $tasso / 100, 2));
    }

    private function buildName(Entity $entity, Entity $quote): string
    {
        $clientLabel = trim((string) ($quote->get('billingContactName') ?: $quote->get('accountName') ?: ''));
        $tipo = $entity->get('tipo');
        $importo = $this->toFloat($entity->get('importo'));
        $parts = [];

        if ($clientLabel !== '') {
            $parts[] = $clientLabel;
        }

        if (is_string($tipo) && $tipo !== '') {
            $parts[] = $tipo;
        }

        $parts[] = '€. ' . number_format($importo, 2, '.', '');

        return strtoupper(implode(' - ', $parts));
    }

    private function toFloat(mixed $value): float
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return 0.0;
        }

        return (float) $value;
    }
}
