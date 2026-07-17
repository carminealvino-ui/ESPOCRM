<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Custom\Services\ProvvigioneStatusSync;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Aggiorna lo stato delle provvigioni collegate quando cambia lo stato contratto.
 */
class SyncProvvigioniStato implements AfterSave
{
    public static int $order = 18;

    public function __construct(
        private ProvvigioneStatusSync $statusSync
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('silent') || $options->get('skipHooks')) {
            return;
        }

        if (!$entity->getId()) {
            return;
        }

        if (!$entity->isNew() && !$entity->isAttributeChanged('statoContratto')) {
            return;
        }

        $this->statusSync->syncProvvigioniForQuote($entity);
    }
}
