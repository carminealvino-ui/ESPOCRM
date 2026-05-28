<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Custom\Services\QuotePricingCalculator;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Calcolo automatico minus/plus (plusvalenza) e totali prezzo codice sul contratto.
 */
class SyncMinusPlus implements BeforeSave
{
    public static int $order = 8;

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
