<?php

namespace Espo\Custom\Hooks\Opportunity;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Custom\Services\QuotePricingCalculator;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Da UI: prezzo codice e minus/plus su opportunità con articoli (es. COMBO 9+9).
 */
class SyncContractPricing implements BeforeSave
{
    public static int $order = 98;

    public function __construct(
        private QuotePricingCalculator $pricingCalculator
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('silent') || $options->get('skipHooks')) {
            return;
        }

        $this->pricingCalculator->syncOpportunityOnBeforeSave($entity);
    }
}
