<?php

namespace Espo\Custom\Hooks\ProductPrice;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Custom\Services\IvaDualPriceSync;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Listino e prezzo codice: modifica IVA inclusa o esclusa → calcolo automatico dell'altro.
 *
 * @implements BeforeSave<Entity>
 * @implements AfterSave<Entity>
 */
class DualIvaPricing implements BeforeSave, AfterSave
{
    public static int $order = 5;

    public function __construct(
        private IvaDualPriceSync $ivaDualPriceSync
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($entity->getEntityType() !== 'ProductPrice') {
            return;
        }

        $this->ivaDualPriceSync->syncProductPriceOnBeforeSave($entity);
    }

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($entity->getEntityType() !== 'ProductPrice') {
            return;
        }

        if ($options->has('silent') || $options->has('skipHooks')) {
            return;
        }

        $this->ivaDualPriceSync->syncProductFromProductPrice($entity);
    }
}
