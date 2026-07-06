<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\BeforeSave as BeforeSaveHook;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Prezzi codice / totali: SyncContractPricing + QuotePricingCalculator (order 999).
 */
class BeforeSave implements BeforeSaveHook
{
    public static int $order = 5;

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        // Intenzionalmente vuoto: evita conflitto con QuotePricingCalculator (SyncContractPricing).
    }
}
