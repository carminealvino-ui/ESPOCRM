<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Custom\Services\QuoteTotaleProvvigioniService;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Mantiene totaleProvvigioni allineato dopo ogni salvataggio contratto.
 */
class SyncTotaleProvvigioni implements AfterSave
{
    public static int $order = 25;

    public function __construct(
        private QuoteTotaleProvvigioniService $totaleProvvigioniService
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('silent') || $options->get('skipHooks')) {
            return;
        }

        $quoteId = $entity->getId();

        if (!$quoteId) {
            return;
        }

        $this->totaleProvvigioniService->syncForQuoteId($quoteId);
    }
}
