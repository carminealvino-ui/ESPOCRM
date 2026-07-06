<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Custom\Services\ProvvigioneManager;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Ricalcola provvigioni consolidate ad ogni salvataggio contratto.
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

        if (!$entity->get('opportunityId')) {
            return;
        }

        $this->provvigioneManager->recalculateAllForQuote($entity);
    }
}
