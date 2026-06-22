<?php

namespace Espo\Custom\Hooks\Provvigione;

use Espo\Core\Hook\Hook\AfterRemove;
use Espo\Core\Hook\Hook\AfterSave;
use Espo\Custom\Services\QuoteProvvigioniSync;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\RemoveOptions;
use Espo\ORM\Repository\Option\SaveOptions;

/**
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
        if ($options->get('skipHooks') || $options->get('skipProvvigioniQuoteSync')) {
            return;
        }

        $this->sync($entity);
    }

    public function afterRemove(Entity $entity, RemoveOptions $options): void
    {
        if ($options->get('skipHooks') || $options->get('skipProvvigioniQuoteSync')) {
            return;
        }

        $this->sync($entity);
    }

    private function sync(Entity $entity): void
    {
        $quoteId = $entity->get('contrattoId');

        if (!$quoteId) {
            return;
        }

        $this->quoteProvvigioniSync->syncTotaleProvvigioniOnQuote($quoteId);
    }
}
