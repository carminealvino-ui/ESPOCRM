<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Custom\Services\QuotePricingCalculator;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Sales Pack ricalcola spesso totali dal listino dopo BeforeSave: riallinea a importoContratto.
 */
class SyncContractPricingAfterSave implements AfterSave
{
    public static int $order = 5;

    public function __construct(
        private QuotePricingCalculator $pricingCalculator,
        private EntityManager $entityManager
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('silent') || $options->get('skipHooks')) {
            return;
        }

        if ($options->get('skipContractPricingAfter')) {
            return;
        }

        if (!$this->pricingCalculator->needsContractPricingResync($entity)) {
            return;
        }

        $fresh = $this->entityManager->getEntityById('Quote', $entity->getId());

        if (!$fresh) {
            return;
        }

        $this->pricingCalculator->syncOnBeforeSave($fresh);

        $this->entityManager->saveEntity($fresh, [
            'skipContractPricingAfter' => true,
        ]);
    }
}
