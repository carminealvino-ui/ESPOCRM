<?php

namespace Espo\Custom\Hooks\Product;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Custom\Services\IvaDualPriceSync;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Pannello Prezzo Product: calcolo automatico IVA inclusa/esclusa (listino e codice).
 *
 * @implements BeforeSave<Entity>
 */
class DualIvaPricing implements BeforeSave
{
    public static int $order = 5;

    public function __construct(
        private IvaDualPriceSync $ivaDualPriceSync
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($entity->getEntityType() !== 'Product') {
            return;
        }

        if ($options->has('silent') || $options->has('skipHooks')) {
            return;
        }

        $this->ivaDualPriceSync->syncProductOnBeforeSave($entity);
    }
}
