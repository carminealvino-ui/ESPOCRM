<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Custom\Services\ProvvigioneManager;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Aggiorna totale provvigioni sul contratto dopo salvataggio.
 */
class AfterSaveTotaleProvvigioni implements AfterSave
{
    public static int $order = 20;

    public function __construct(
        private ProvvigioneManager $provvigioneManager
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('skipHooks') || $options->get('silent')) {
            return;
        }

        $this->provvigioneManager->refreshQuoteTotaleProvvigioni($entity);
    }
}
