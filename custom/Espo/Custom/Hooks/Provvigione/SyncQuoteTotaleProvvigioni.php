<?php

namespace Espo\Custom\Hooks\Provvigione;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Custom\Services\QuoteProvvigioniSync;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * @implements AfterSave<Entity>
 */
class SyncQuoteTotaleProvvigioni implements AfterSave
{
    public static int $order = 20;

    public function __construct(
        private QuoteProvvigioniSync $quoteProvvigioniSync
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('skipHooks') || $options->get('silent')) {
            return;
        }

        $quoteId = $entity->get('contrattoId');

        if (!$quoteId) {
            return;
        }

        $this->quoteProvvigioniSync->syncTotaleProvvigioniOnQuote($quoteId);
    }
}
