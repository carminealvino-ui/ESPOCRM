<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Custom\Services\ProvvigioneManager;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Ricalcola provvigione consolidata quando cambiano importi o date attivazione.
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
        if ($options->get('silent')) {
            return;
        }

        if (!$entity->get('opportunityId')) {
            return;
        }

        $watch = [
            'amount',
            'importoContratto',
            'dataAttivazione',
            'dataInstallazione',
            'productCategoryId',
            'minusPlus',
        ];

        $changed = false;

        foreach ($watch as $field) {
            if ($entity->isAttributeChanged($field)) {
                $changed = true;
                break;
            }
        }

        if (!$changed && !$entity->isNew()) {
            return;
        }

        $opportunity = $this->entityManager->getEntityById(
            'Opportunity',
            $entity->get('opportunityId')
        );

        if (!$opportunity) {
            return;
        }

        $this->provvigioneManager->createConsolidataForQuote($opportunity, $entity);
    }
}
