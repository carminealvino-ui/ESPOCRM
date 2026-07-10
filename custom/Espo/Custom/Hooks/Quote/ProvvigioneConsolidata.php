<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Custom\Services\ProvvigioneManager;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Ricalcola tutte le provvigioni consolidate quando cambiano importi articoli.
 */
class ProvvigioneConsolidata implements AfterSave
{
    public static int $order = 15;

    public function __construct(
        private EntityManager $entityManager,
        private ProvvigioneManager $provvigioneManager
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('silent') || $options->get('skipHooks')) {
            return;
        }

        if (!$entity->getId()) {
            return;
        }

        if (!$this->shouldRecalculate($entity)) {
            return;
        }

        $quote = $this->entityManager->getEntityById('Quote', $entity->getId());

        if (!$quote || !$quote->get('opportunityId')) {
            return;
        }

        $this->provvigioneManager->recalculateAllForQuote($quote);
    }

    private function shouldRecalculate(Entity $entity): bool
    {
        if ($entity->isNew()) {
            return (bool) $entity->get('opportunityId');
        }

        $watch = [
            'itemList',
            'amount',
            'taxAmount',
            'grandTotalAmount',
            'totalPrezzoCodice',
            'prezzoCodiceIvaEsclusa',
            'minusPlus',
            'importoContratto',
            'isTaxInclusive',
            'numeroContratto',
            'number',
            'dataAttivazione',
            'dataInstallazione',
            'dateQuoted',
            'productCategoryId',
            'prezzoListinoIvaEsclusa',
            'margineSuListino',
            'contattoPersonaleArquati',
        ];

        foreach ($watch as $field) {
            if ($entity->isAttributeChanged($field)) {
                return true;
            }
        }

        return false;
    }
}
