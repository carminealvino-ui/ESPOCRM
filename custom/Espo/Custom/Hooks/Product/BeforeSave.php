<?php

namespace Espo\Custom\Hooks\Product;

use Espo\Core\Hook\Hook\BeforeSave as BeforeSaveHook;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Forza nome prodotto in formato:
 * BRAND - CATEGORIA - DENOMINAZIONE
 */
class BeforeSave implements BeforeSaveHook
{
    public static int $order = 99;

    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        $denominazione = trim((string) ($entity->get('denominazione') ?? ''));

        if ($denominazione === '') {
            return;
        }

        $brandName = $this->resolveLinkedName($entity, 'brandId', 'ProductBrand', 'brandName');
        $categoryName = $this->resolveLinkedName($entity, 'categoryId', 'ProductCategory', 'categoryName');

        if ($brandName === '' || $categoryName === '') {
            return;
        }

        $target = sprintf('%s - %s - %s', $brandName, $categoryName, $denominazione);

        if ((string) $entity->get('name') !== $target) {
            $entity->set('name', $target);
        }

        $this->syncPrezzoCodiceIvaInclusa($entity);
    }

    /**
     * Mantiene prezzo codice IVI (minus/plus B2C) allineato al netto su scheda prodotto.
     */
    private function syncPrezzoCodiceIvaInclusa(Entity $entity): void
    {
        if (!$entity->hasAttribute('prezzoCodiceIvaInclusa')) {
            return;
        }

        $net = $entity->get('prezzoCodice');

        if ($net === null || $net === '') {
            return;
        }

        if ($entity->isAttributeChanged('prezzoCodice')
            || !$entity->get('prezzoCodiceIvaInclusa')) {
            $entity->set(
                'prezzoCodiceIvaInclusa',
                round((float) $net * 1.1, 2)
            );
        }
    }

    private function resolveLinkedName(
        Entity $entity,
        string $idField,
        string $entityType,
        string $cachedNameField
    ): string {
        $cached = trim((string) ($entity->get($cachedNameField) ?? ''));

        if ($cached !== '') {
            return $cached;
        }

        $id = (string) ($entity->get($idField) ?? '');

        if ($id === '') {
            return '';
        }

        $linked = $this->entityManager->getEntityById($entityType, $id);

        return $linked ? trim((string) ($linked->get('name') ?? '')) : '';
    }
}
