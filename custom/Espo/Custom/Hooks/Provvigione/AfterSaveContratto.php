<?php

namespace Espo\Custom\Hooks\Provvigione;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Custom\Services\ProvvigioneManager;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Aggiorna totale provvigioni sul contratto collegato.
 */
class AfterSaveContratto implements AfterSave
{
    public static int $order = 12;

    public function __construct(
        private EntityManager $entityManager,
        private ProvvigioneManager $provvigioneManager
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('silent') || $options->get('skipHooks')) {
            return;
        }

        if (!$entity->get('contrattoId')) {
            return;
        }

        $quote = $this->entityManager->getEntityById('Quote', $entity->get('contrattoId'));

        if (!$quote) {
            return;
        }

        $this->provvigioneManager->refreshQuoteTotaleProvvigioni($quote);
    }
}
