<?php

namespace Espo\Custom\Hooks\Product;

use Espo\Core\Hook\Hook\BeforeSave as BeforeSaveHook;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Nome prodotto in formato catalogo:
 * BRAND - {nn} CATEGORIA - {nnn} DENOMINAZIONE
 * Es.: ARIEL - 01 CALDAIE A GAS - 001 ECO WIND 24 kW
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

        $categoryPart = $this->buildCategoryLabel($entity, $categoryName);
        $denominazionePart = $this->buildDenominazioneLabel($entity, $denominazione);

        $target = sprintf('%s - %s - %s', $brandName, $categoryPart, $denominazionePart);

        if ((string) $entity->get('name') !== $target) {
            $entity->set('name', $target);
        }
    }

    private function buildCategoryLabel(Entity $entity, string $categoryName): string
    {
        $categoryElenco = $this->resolveCategoryElenco($entity);

        if ($categoryElenco === '') {
            return $categoryName;
        }

        return $categoryElenco . ' ' . $categoryName;
    }

    private function buildDenominazioneLabel(Entity $entity, string $denominazione): string
    {
        $productElenco = $this->formatElenco($entity->get('elencoCatalogo'), 3);

        if ($productElenco === '') {
            return $denominazione;
        }

        return $productElenco . ' ' . $denominazione;
    }

    private function resolveCategoryElenco(Entity $entity): string
    {
        $categoryId = (string) ($entity->get('categoryId') ?? '');

        if ($categoryId === '') {
            return '';
        }

        $category = $this->entityManager->getEntityById('ProductCategory', $categoryId);

        if (!$category) {
            return '';
        }

        return $this->formatElenco($category->get('elencoCatalogo'), 2);
    }

    private function formatElenco(mixed $value, int $length): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (!is_numeric($value)) {
            return trim((string) $value);
        }

        return str_pad((string) (int) $value, $length, '0', STR_PAD_LEFT);
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
