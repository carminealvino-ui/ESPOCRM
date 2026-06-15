<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Allinea lineaProdotto (enum legacy) e productCategory (link anagrafica).
 * Produzione: product_category non ha colonna linea_prodotto — mappa per nome.
 */
class LineaProdottoCategorySync
{
    /** @var array<string, string> linea enum → nome ProductCategory */
    private const LINEA_TO_CATEGORY_NAME = [
        'Climatizzazione' => 'CLIMATIZZATORI',
        'Caldaie' => 'CALDAIE A GAS',
        'Stufe' => 'BIOMASSA',
        'Biomassa' => 'BIOMASSA',
        'Bioetanolo' => 'BIOMASSA',
        'Fotovoltaico' => 'FOTOVOLTAICO',
        'Pergole' => 'PERGOLA',
        'Tende da Sole' => 'TENDA A BRACCI',
        'Chiusure Verticali' => 'TENDA VERTICALE',
        'Vetrate' => 'VETROTENDA',
        'TLC' => 'VODAFONE VOCE',
        'TLC - Vodafone' => 'VODAFONE VOCE',
        'Energia' => 'ENEL BUSINESS',
        'Rental' => null,
    ];

    /** @var array<string, string> nome ProductCategory → linea enum */
    private const CATEGORY_NAME_TO_LINEA = [
        'CLIMATIZZATORI' => 'Climatizzazione',
        'CLIMATIZZAZIONE' => 'Climatizzazione',
        'CALDAIE A GAS' => 'Caldaie',
        'CALDAIE' => 'Caldaie',
        'STUFE' => 'Stufe',
        'STUFE A PELLET' => 'Stufe',
        'BIOMASSA' => 'Biomassa',
        'FOTOVOLTAICO' => 'Fotovoltaico',
        'PERGOLA' => 'Pergole',
        'BIOCLIMATICA' => 'Pergole',
        'TENDA A BRACCI' => 'Tende da Sole',
        'TENDA A CUPOLA' => 'Tende da Sole',
        'TENDA VERTICALE' => 'Chiusure Verticali',
        'CHIUSURE VERTICALI' => 'Chiusure Verticali',
        'VETROTENDA' => 'Vetrate',
        'VETRATA IMPACCHETTABILE' => 'Vetrate',
        'VETRATA SCORREVOLE' => 'Vetrate',
        'VODAFONE VOCE' => 'TLC - Vodafone',
        'ENEL BUSINESS' => 'Energia',
    ];

    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function lineaToCategoryName(?string $linea): ?string
    {
        if ($linea === null || trim($linea) === '') {
            return null;
        }

        $linea = trim($linea);

        return self::LINEA_TO_CATEGORY_NAME[$linea] ?? null;
    }

    public function categoryNameToLinea(?string $categoryName): ?string
    {
        if ($categoryName === null || trim($categoryName) === '') {
            return null;
        }

        $key = strtoupper(trim($categoryName));

        return self::CATEGORY_NAME_TO_LINEA[$key] ?? null;
    }

    public function alignOnEntity(Entity $entity): void
    {
        if (!$entity->hasAttribute('productCategoryId')) {
            return;
        }

        $linea = $entity->hasAttribute('lineaProdotto')
            ? $entity->get('lineaProdotto')
            : null;

        $categoryId = $entity->get('productCategoryId');

        if (!$categoryId && $linea) {
            $this->applyCategoryFromLinea($entity, $linea);

            return;
        }

        if ($categoryId && $entity->hasAttribute('lineaProdotto') && !$linea) {
            $this->applyLineaFromCategory($entity, $categoryId);
        }
    }

    private function applyCategoryFromLinea(Entity $entity, string $linea): void
    {
        $categoryName = $this->lineaToCategoryName($linea);

        if (!$categoryName) {
            return;
        }

        $where = [
            'name' => $categoryName,
        ];

        $brandId = $entity->get('productBrandId');

        if ($brandId) {
            $withBrand = $this->entityManager
                ->getRepository('ProductCategory')
                ->where(array_merge($where, ['productBrandId' => $brandId]))
                ->findOne();

            if ($withBrand) {
                $this->setCategoryOnEntity($entity, $withBrand);

                return;
            }
        }

        $category = $this->entityManager
            ->getRepository('ProductCategory')
            ->where($where)
            ->findOne();

        if ($category) {
            $this->setCategoryOnEntity($entity, $category);
        }
    }

    private function applyLineaFromCategory(Entity $entity, string $categoryId): void
    {
        $category = $this->entityManager->getEntityById(
            'ProductCategory',
            $categoryId
        );

        if (!$category) {
            return;
        }

        if ($category->hasAttribute('lineaProdotto') && $category->get('lineaProdotto')) {
            $entity->set('lineaProdotto', $category->get('lineaProdotto'));

            return;
        }

        $linea = $this->categoryNameToLinea($category->get('name'));

        if ($linea) {
            $entity->set('lineaProdotto', $linea);
        }
    }

    private function setCategoryOnEntity(Entity $entity, Entity $category): void
    {
        $entity->set('productCategoryId', $category->getId());
        $entity->set('productCategoryName', $category->get('name'));
    }
}
