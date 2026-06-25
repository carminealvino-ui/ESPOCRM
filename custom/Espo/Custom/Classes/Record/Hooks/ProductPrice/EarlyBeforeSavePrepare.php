<?php

namespace Espo\Custom\Classes\Record\Hooks\ProductPrice;

use Espo\Core\Record\Hook\SaveHook;
use Espo\Custom\Services\IvaDualPriceSync;
use Espo\ORM\Entity;

/**
 * Popola price (e coppie IVA) dai campi dual-IVA prima della validazione record.
 *
 * @implements SaveHook<Entity>
 */
class EarlyBeforeSavePrepare implements SaveHook
{
    public function __construct(
        private IvaDualPriceSync $ivaDualPriceSync
    ) {}

    public function process(Entity $entity): void
    {
        if ($entity->getEntityType() !== 'ProductPrice') {
            return;
        }

        $this->ivaDualPriceSync->syncProductPriceOnBeforeSave($entity);
    }
}
