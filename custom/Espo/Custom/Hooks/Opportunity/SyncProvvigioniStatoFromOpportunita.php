<?php

namespace Espo\Custom\Hooks\Opportunity;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Custom\Services\ProvvigioneManager;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Sincronizza statoProvvigione quando cambia statoContratto / finanziamento su Opportunity.
 */
class SyncProvvigioniStatoFromOpportunita implements AfterSave
{
    public static int $order = 20;

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

        if (
            !$entity->isNew()
            && !$entity->isAttributeChanged('statoContratto')
            && !$entity->isAttributeChanged('statoFinanziamento')
            && !$entity->isAttributeChanged('status')
        ) {
            return;
        }

        $quotes = $this->entityManager
            ->getRDBRepository('Quote')
            ->where(['opportunityId' => $entity->getId()])
            ->find();

        foreach ($quotes as $quote) {
            $this->provvigioneManager->syncProvvigioniStatoForQuote($quote);
        }
    }
}
