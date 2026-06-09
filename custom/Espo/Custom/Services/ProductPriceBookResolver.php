<?php

namespace Espo\Custom\Services;

use Espo\Core\Utils\Config;
use Espo\Modules\Sales\Tools\Price\DefaultPriceBookProvider;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Risolve il listino prezzi per un Product (priorità: brand → default → esistente → nome brand).
 */
class ProductPriceBookResolver
{
    public function __construct(
        private EntityManager $entityManager,
        private DefaultPriceBookProvider $defaultPriceBookProvider,
        private Config $config,
    ) {}

    public function resolveForProduct(Entity $product): ?Entity
    {
        $fromBrand = $this->resolveFromBrand($product);

        if ($fromBrand) {
            return $fromBrand;
        }

        $fromDefault = $this->defaultPriceBookProvider->get();

        if ($fromDefault) {
            return $fromDefault;
        }

        $defaultId = $this->config->get('defaultPriceBookId');

        if ($defaultId) {
            $fromConfig = $this->entityManager->getEntityById('PriceBook', $defaultId);

            if ($fromConfig && $fromConfig->get('status') === 'Active') {
                return $fromConfig;
            }
        }

        $fromExisting = $this->resolveFromExistingProductPrice($product->getId());

        if ($fromExisting) {
            return $fromExisting;
        }

        $fromBrandName = $this->resolveByBrandName($product);

        if ($fromBrandName) {
            return $fromBrandName;
        }

        foreach (['ARIEL Energia', 'ARIEL', 'Energia'] as $namePattern) {
            $priceBook = $this->findActivePriceBookByName($namePattern);

            if ($priceBook) {
                return $priceBook;
            }
        }

        return $this->entityManager
            ->getRDBRepository('PriceBook')
            ->where(['status' => 'Active'])
            ->order('name', 'ASC')
            ->findOne();
    }

    private function resolveFromBrand(Entity $product): ?Entity
    {
        $brandId = (string) ($product->get('brandId') ?? '');

        if ($brandId === '') {
            return null;
        }

        $brand = $this->entityManager->getEntityById('ProductBrand', $brandId);

        if (!$brand) {
            return null;
        }

        $priceBookId = (string) ($brand->get('priceBookId') ?? '');

        if ($priceBookId === '') {
            return null;
        }

        $priceBook = $this->entityManager->getEntityById('PriceBook', $priceBookId);

        if (!$priceBook || $priceBook->get('status') !== 'Active') {
            return null;
        }

        return $priceBook;
    }

    private function resolveByBrandName(Entity $product): ?Entity
    {
        $brandName = $this->resolveBrandName($product);

        if ($brandName === '') {
            return null;
        }

        foreach ([$brandName . ' Energia', $brandName] as $namePattern) {
            $priceBook = $this->findActivePriceBookByName($namePattern);

            if ($priceBook) {
                return $priceBook;
            }
        }

        return null;
    }

    private function resolveBrandName(Entity $product): string
    {
        $cached = trim((string) ($product->get('brandName') ?? ''));

        if ($cached !== '') {
            return $cached;
        }

        $brandId = (string) ($product->get('brandId') ?? '');

        if ($brandId === '') {
            return '';
        }

        $brand = $this->entityManager->getEntityById('ProductBrand', $brandId);

        return $brand ? trim((string) ($brand->get('name') ?? '')) : '';
    }

    private function resolveFromExistingProductPrice(?string $productId): ?Entity
    {
        if (!$productId) {
            return null;
        }

        $productPrice = $this->entityManager
            ->getRDBRepository('ProductPrice')
            ->where([
                'productId' => $productId,
                'status' => 'Active',
            ])
            ->order('dateStart', 'DESC')
            ->findOne();

        if (!$productPrice) {
            return null;
        }

        $priceBookId = $productPrice->get('priceBookId');

        if (!$priceBookId) {
            return null;
        }

        return $this->entityManager->getEntityById('PriceBook', $priceBookId);
    }

    private function findActivePriceBookByName(string $namePattern): ?Entity
    {
        return $this->entityManager
            ->getRDBRepository('PriceBook')
            ->where([
                'name*' => $namePattern,
                'status' => 'Active',
            ])
            ->order('name', 'DESC')
            ->findOne();
    }
}
