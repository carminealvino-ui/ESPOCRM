<?php

namespace Espo\Custom\Classes\Record\Hooks\ProductPrice;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Record\Hook\SaveHook;
use Espo\Custom\Services\IvaDualPriceSync;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Calcola price e coppie IVA prima della validazione Record (campo price obbligatorio in Sales).
 *
 * @implements SaveHook<Entity>
 */
class EarlyBeforeSavePrepare implements SaveHook
{
    public function __construct(
        private IvaDualPriceSync $ivaDualPriceSync,
        private EntityManager $entityManager,
    ) {}

    public function process(Entity $entity): void
    {
        if ($entity->getEntityType() !== 'ProductPrice') {
            return;
        }

        $this->ensureProductRelation($entity);
        $this->ivaDualPriceSync->syncProductPriceOnBeforeSave($entity);

        $price = $entity->get('price');

        if ($price !== null && $price !== '' && is_numeric($price) && (float) $price > 0) {
            return;
        }

        throw new BadRequest(
            'Inserire almeno un prezzo listino (IVA inclusa o esclusa) per calcolare il prezzo.'
        );
    }

    private function ensureProductRelation(Entity $entity): void
    {
        $productId = (string) ($entity->get('productId') ?? '');

        if ($productId === '') {
            return;
        }

        $product = $this->entityManager->getEntityById('Product', $productId);

        if ($product) {
            $entity->set('product', $product);
        }
    }
}
