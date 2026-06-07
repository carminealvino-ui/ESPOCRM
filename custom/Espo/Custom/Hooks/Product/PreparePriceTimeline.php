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
    /** @var array<int, string> */
    public static array $pendingDateByObject = [];

    public static int $order = 4;

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

        if (!$this->productPriceTimeline->hasPricePanelChanges($entity)) {
            return;
        }

        $dateStart = trim((string) ($entity->get('dataInizioValidita') ?? ''));

        if ($dateStart === '') {
            $dateStart = date('Y-m-d');
            $entity->set('dataInizioValidita', $dateStart);
        }

        self::$pendingDateByObject[spl_object_id($entity)] = $dateStart;
    }
}
