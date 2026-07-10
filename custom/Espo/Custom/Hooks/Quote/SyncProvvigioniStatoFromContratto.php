<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Custom\Services\ProvvigioneManager;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Sincronizza statoProvvigione quando cambia lo stato del contratto (Quote.status).
 */
class SyncProvvigioniStatoFromContratto implements AfterSave
{
    public static int $order = 20;

    public function __construct(
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

        if (!$entity->isNew() && !$entity->isAttributeChanged('status') && !$entity->isAttributeChanged('statoContratto')) {
            return;
        }

        $this->provvigioneManager->syncProvvigioniStatoForQuote($entity);
    }
}
