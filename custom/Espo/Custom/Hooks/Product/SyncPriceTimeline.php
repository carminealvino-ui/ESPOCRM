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

        $dateStart = $this->resolvePendingDateStart($entity);

        if ($dateStart === null) {
            return;
        }

        try {
            $synced = $this->productPriceTimeline->syncFromProduct($entity, $dateStart);

            if (!$synced) {
                $this->log->debug(
                    'Product price timeline: nessuna nuova riga (valori già allineati) per prodotto '
                    . ($entity->getId() ?? '')
                );
            }
        } catch (\Throwable $e) {
            $this->log->error(
                'Product price timeline sync failed [product='
                . ($entity->getId() ?? 'new')
                . ']: '
                . $e->getMessage()
            );
        }
    }

    private function resolvePendingDateStart(Entity $entity): ?string
    {
        $keys = [];

        if ($entity->getId()) {
            $keys[] = 'id:' . $entity->getId();
        }

        $keys[] = PreparePriceTimeline::pendingKey($entity);

        foreach ($keys as $key) {
            if (!isset(PreparePriceTimeline::$pendingDateByKey[$key])) {
                continue;
            }

            $dateStart = PreparePriceTimeline::$pendingDateByKey[$key];
            unset(PreparePriceTimeline::$pendingDateByKey[$key]);

            return $dateStart;
        }

        if (!$this->productPriceTimeline->needsBackfillSync($entity)) {
            return null;
        }

        return $this->productPriceTimeline->resolveDateStart($entity);
    }
}
