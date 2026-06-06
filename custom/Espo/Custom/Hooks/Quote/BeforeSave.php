<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hooks\Base;
use Espo\ORM\Entity;

/**
 * Prezzi codice / totali: SyncContractPricing + QuotePricingCalculator (order 999).
 */
class BeforeSave extends Base
{
    public function beforeSave(Entity $entity, array $options): void
    {
        // Intenzionalmente vuoto: evita conflitto con QuotePricingCalculator.
    }
}
