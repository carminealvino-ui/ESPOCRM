<?php

namespace Espo\Custom\Hooks\Provvigione;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Custom\Services\ProvvigioneManager;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Aggiorna Quote.totaleProvvigioni quando cambia una Provvigione.
 */
class AfterSaveRefreshQuoteTotale implements AfterSave
{
    public static int $order = 20;

    public function __construct(
        private EntityManager $entityManager,
        private ProvvigioneManager $provvigioneManager
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('skipHooks') || $options->get('silent')) {
            return;
        }

        $quoteId = $entity->get('contrattoId');

        if (!$quoteId) {
            return;
        }

        $quote = $this->entityManager->getEntityById('Quote', $quoteId);

        if (!$quote) {
            return;
        }

        $this->provvigioneManager->refreshQuoteTotaleProvvigioni($quote);
    }
}
