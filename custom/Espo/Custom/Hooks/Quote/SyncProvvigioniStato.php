<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Custom\Services\ProvvigioneStatusSync;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Allinea stato provvigioni al variare dello stato contratto (Quote).
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

        $watch = [
            'statoContratto',
            'dataInstallazione',
            'dataAttivazione',
            'importoCaparra',
            'importoContratto',
            'amount',
            'dateOrdered',
            'dateQuoted',
        ];

        $changed = $entity->isNew();

        foreach ($watch as $field) {
            if ($entity->isAttributeChanged($field)) {
                $changed = true;
                break;
            }
        }

        if (!$changed) {
            return;
        }

        $this->statusSync->syncProvvigioniForQuote($entity);
    }
}
