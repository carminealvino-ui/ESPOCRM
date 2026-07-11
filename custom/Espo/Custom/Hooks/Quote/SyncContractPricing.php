<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Custom\Services\QuotePricingCalculator;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Contratto: prezzi riga IVA inclusa se flag attivo; importo/minus-plus da importoContratto.
 */
class SyncContractPricing implements BeforeSave
{
    public static int $order = 999;

    public function __construct(
        private QuotePricingCalculator $pricingCalculator
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('silent') || $options->get('skipHooks')) {
            return;
        }

        $this->pricingCalculator->syncOnBeforeSave($entity);
    }
}
