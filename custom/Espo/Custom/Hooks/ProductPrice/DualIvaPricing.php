<?php

namespace Espo\Custom\Hooks\ProductPrice;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Core\Utils\Log;
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
        private IvaDualPriceSync $ivaDualPriceSync,
        private Log $log,
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

        try {
            $this->ivaDualPriceSync->syncProductFromProductPrice($entity);
        } catch (\Throwable $e) {
            $this->log->error(
                'ProductPrice sync to Product failed [productPrice='
                . ($entity->getId() ?? 'new')
                . ']: '
                . $e->getMessage()
            );
        }
    }
}
