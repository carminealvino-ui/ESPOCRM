<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Custom\Services\QuotePricingCalculator;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Riallinea totali e minusPlus dopo il ricalcolo standard Espo sulle righe.
 */
class AfterSaveSyncContractTotals implements AfterSave
{
    public static int $order = 5;

    public function __construct(
        private EntityManager $entityManager,
        private QuotePricingCalculator $pricingCalculator
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('silent') || $options->get('skipHooks') || $options->get('contractPricingSync')) {
            return;
        }

        $fresh = $this->entityManager->getEntityById('Quote', $entity->getId());

        if (!$fresh) {
            return;
        }

        $importo = $this->pricingCalculator->resolveImportoContrattoForQuote($fresh);

        if ($importo === null || $importo <= 0) {
            return;
        }

        $grandTotal = (float) ($fresh->get('grandTotalAmount') ?? 0);
        $minusPlus = $fresh->get('minusPlus');
        $needsTotals = abs($grandTotal - $importo) > 0.02;
        $needsMinusPlus = $minusPlus === null || $minusPlus === '';

        if (!$needsTotals && !$needsMinusPlus) {
            return;
        }

        $this->pricingCalculator->syncOnBeforeSave($fresh);

        $this->entityManager->saveEntity($fresh, [
            'silent' => true,
            'skipHooks' => true,
            'contractPricingSync' => true,
        ]);
    }
}
