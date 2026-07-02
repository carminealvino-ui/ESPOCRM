<?php

namespace Espo\Custom\Hooks\Provvigione;

use Espo\Core\Hook\Hook\AfterRemove;
use Espo\Core\Hook\Hook\AfterSave;
use Espo\Custom\Services\QuoteTotaleProvvigioniService;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Ricalcola totaleProvvigioni sul contratto quando cambiano le righe provvigione.
 */
class UpdateQuoteTotaleProvvigioni implements AfterSave, AfterRemove
{
    public static int $order = 20;

    public function __construct(
        private QuoteTotaleProvvigioniService $totaleProvvigioniService
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        $this->sync($entity);
    }

    public function afterRemove(Entity $entity): void
    {
        $this->sync($entity);
    }

    private function sync(Entity $entity): void
    {
        $contrattoId = $entity->get('contrattoId');

        if (!$contrattoId) {
            return;
        }

        $this->totaleProvvigioniService->syncForQuoteId($contrattoId);
    }
}
