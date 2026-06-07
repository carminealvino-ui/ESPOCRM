<?php

namespace Espo\Custom\Hooks\Product;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Custom\Services\ProductPriceTimeline;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Default data inizio validità quando cambiano i prezzi dal pannello Product.
 *
 * @implements BeforeSave<Entity>
 */
class PreparePriceTimeline implements BeforeSave
{
    /** @var array<string, string> */
    public static array $pendingDateByKey = [];

    public static int $order = 6;

    public function __construct(
        private ProductPriceTimeline $productPriceTimeline
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($entity->getEntityType() !== 'Product') {
            return;
        }

        if ($options->has('silent') || $options->has('skipHooks')) {
            return;
        }

        if (!$this->productPriceTimeline->shouldSyncFromProduct($entity)) {
            return;
        }

        $dateStart = $this->productPriceTimeline->resolveDateStart($entity);

        if (trim((string) ($entity->get('dataInizioValidita') ?? '')) === '') {
            $entity->set('dataInizioValidita', $dateStart);
        }

        self::$pendingDateByKey[self::pendingKey($entity)] = $dateStart;
    }

    public static function pendingKey(Entity $entity): string
    {
        $id = $entity->getId();

        if ($id) {
            return 'id:' . $id;
        }

        return 'obj:' . spl_object_id($entity);
    }
}
