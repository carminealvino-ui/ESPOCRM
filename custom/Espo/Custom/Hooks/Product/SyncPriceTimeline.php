<?php

namespace Espo\Custom\Hooks\Product;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Core\Utils\Log;
use Espo\Custom\Services\ProductPriceTimeline;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Pannello Prezzo su Product → nuova riga ProductPrice + chiusura riga precedente.
 *
 * @implements AfterSave<Entity>
 */
class SyncPriceTimeline implements AfterSave
{
    public static int $order = 20;

    public function __construct(
        private ProductPriceTimeline $productPriceTimeline,
        private Log $log,
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($entity->getEntityType() !== 'Product') {
            return;
        }

        if ($options->has('silent') || $options->has('skipHooks')) {
            return;
        }

        $objectId = spl_object_id($entity);

        if (!isset(PreparePriceTimeline::$pendingDateByObject[$objectId])) {
            return;
        }

        $dateStart = PreparePriceTimeline::$pendingDateByObject[$objectId];
        unset(PreparePriceTimeline::$pendingDateByObject[$objectId]);

        try {
            $this->productPriceTimeline->syncFromProduct($entity, $dateStart);
        } catch (\Throwable $e) {
            $this->log->error('Product price timeline sync failed: ' . $e->getMessage());
        }
    }
}
