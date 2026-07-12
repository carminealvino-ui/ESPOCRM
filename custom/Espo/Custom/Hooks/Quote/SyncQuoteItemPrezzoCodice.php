<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Custom\Services\QuotePricingCalculator;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Ogni salvataggio: prezzo codice su ogni riga articolo da prodotto/listino.
 */
class SyncQuoteItemPrezzoCodice implements BeforeSave
{
    public static int $order = 6;

    public function __construct(
        private QuotePricingCalculator $pricingCalculator
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('skipHooks') || $options->get('silent')) {
            return;
        }

        try {
            $this->pricingCalculator->syncItemListPrezzoCodice($entity);
        } catch (\Throwable $e) {
            error_log('SyncQuoteItemPrezzoCodice [' . ($entity->getId() ?? 'new') . ']: ' . $e->getMessage());
        }
    }
}
