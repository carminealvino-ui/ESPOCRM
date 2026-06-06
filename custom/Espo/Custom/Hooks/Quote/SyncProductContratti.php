<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Custom\Services\ProductContrattiSync;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Articoli contratto → relazione Product.contratti (prodotticontratti).
 *
 * @implements AfterSave<Entity>
 */
class SyncProductContratti implements AfterSave
{
    public static int $order = 50;

    public function __construct(
        private ProductContrattiSync $productContrattiSync
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($entity->getEntityType() !== 'Quote') {
            return;
        }

        if ($options->has('silent') || $options->has('skipHooks')) {
            return;
        }

        $this->productContrattiSync->syncFromQuoteItems($entity);
    }
}
