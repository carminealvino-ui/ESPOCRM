<?php

namespace Espo\Custom\Hooks\Provvigione;

use Espo\Core\Hook\Hook\AfterRemove;
use Espo\Core\Hook\Hook\AfterSave;
use Espo\Custom\Services\QuoteProvvigioniSync;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Aggiorna totaleProvvigioni sul contratto collegato.
 *
 * @implements AfterSave<Entity>
 * @implements AfterRemove<Entity>
 */
class SyncQuoteTotaleProvvigioni implements AfterSave, AfterRemove
{
    public static int $order = 20;

    public function __construct(
        private QuoteProvvigioniSync $quoteProvvigioniSync
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        $this->sync($entity, $options);
    }

    public function afterRemove(Entity $entity, SaveOptions $options): void
    {
        $this->sync($entity, $options);
    }

    private function sync(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('skipHooks')) {
            return;
        }

        $quoteId = $entity->get('contrattoId');

        if (!$quoteId) {
            return;
        }

        $this->quoteProvvigioniSync->syncTotaleProvvigioniOnQuote($quoteId);
    }
}
