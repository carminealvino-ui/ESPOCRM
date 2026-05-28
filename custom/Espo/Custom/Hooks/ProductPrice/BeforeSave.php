<?php

namespace Espo\Custom\Hooks\ProductPrice;

use Espo\Core\Hook\Hook\BeforeSave as BeforeSaveHook;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Allinea prezzo codice netto / IVI sulla riga listino (stessa logica del listino ARIEL).
 */
class BeforeSave implements BeforeSaveHook
{
    private const ALIQUOTA_IVA = 10.0;

    public static int $order = 5;

    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if (!$entity->hasAttribute('prezzoCodice') && !$entity->hasAttribute('prezzoCodiceIvaInclusa')) {
            return;
        }

        $taxInclusive = $this->resolvePriceBookTaxInclusive($entity);
        $net = $this->floatOrNull($entity->get('prezzoCodice'));
        $ivi = $this->floatOrNull($entity->get('prezzoCodiceIvaInclusa'));

        if ($ivi !== null && $ivi > 0
            && ($entity->isAttributeChanged('prezzoCodiceIvaInclusa') || $net === null || $net <= 0)) {
            $entity->set(
                'prezzoCodice',
                $taxInclusive
                    ? round($ivi / (1 + self::ALIQUOTA_IVA / 100), 2)
                    : $ivi
            );

            return;
        }

        if ($net !== null && $net > 0
            && ($entity->isAttributeChanged('prezzoCodice') || $ivi === null || $ivi <= 0)) {
            $entity->set(
                'prezzoCodiceIvaInclusa',
                $taxInclusive
                    ? round($net * (1 + self::ALIQUOTA_IVA / 100), 2)
                    : $net
            );
        }
    }

    private function resolvePriceBookTaxInclusive(Entity $productPrice): bool
    {
        $priceBookId = $productPrice->get('priceBookId');

        if (!$priceBookId) {
            return true;
        }

        $priceBook = $this->entityManager->getEntityById('PriceBook', $priceBookId);

        return $priceBook ? (bool) $priceBook->get('isTaxInclusive') : true;
    }

    private function floatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
