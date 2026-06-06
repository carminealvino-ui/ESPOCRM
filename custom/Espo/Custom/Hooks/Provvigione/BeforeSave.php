<?php

namespace Espo\Custom\Hooks\Provvigione;

use Espo\Core\Hook\Hook\AfterRemove;
use Espo\Core\Hook\Hook\AfterSave;
use Espo\Core\Hook\Hook\BeforeSave as BeforeSaveHook;
use Espo\Custom\Services\QuoteProvvigioniSync;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Provvigione manuale: importo = tassoProvvigioni % × importoContratto.
 * Nessuna regola provvigionale, nessuna logica per tipo.
 *
 * @implements BeforeSaveHook<Entity>
 * @implements AfterSave<Entity>
 * @implements AfterRemove<Entity>
 */
class BeforeSave implements BeforeSaveHook, AfterSave, AfterRemove
{
    public static int $order = 5;

    public function __construct(
        private EntityManager $entityManager,
        private QuoteProvvigioniSync $quoteProvvigioniSync
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        $quoteId = $entity->get('contrattoId');

        if (!$quoteId) {
            return;
        }

        $quote = $this->entityManager->getEntityById('Quote', $quoteId);

        if (!$quote) {
            return;
        }

        $this->syncFromQuote($entity, $quote);
        $this->applyImportoFromTassoAndBase($entity, $quote);
        $entity->set('name', $this->buildName($entity, $quote));
    }

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        $this->syncTotale($entity, $options);
    }

    public function afterRemove(Entity $entity, SaveOptions $options): void
    {
        $this->syncTotale($entity, $options);
    }

    private function syncTotale(Entity $entity, SaveOptions $options): void
    {
        if ($options->has('skipHooks')) {
            return;
        }

        $quoteId = $entity->get('contrattoId');

        if (!$quoteId) {
            return;
        }

        $this->quoteProvvigioniSync->syncTotaleProvvigioniOnQuote($quoteId);
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

    /** Imponibile contratto (amount = base provvigioni). */
    private function resolveQuoteImportoContratto(Entity $quote): ?float
    {
        foreach (['amount', 'importoContratto', 'grandTotalAmount'] as $field) {
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
