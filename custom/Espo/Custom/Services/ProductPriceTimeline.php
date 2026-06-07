<?php

namespace Espo\Custom\Services;

use Espo\Core\Exceptions\Error;
use Espo\Modules\Sales\Tools\Price\DefaultPriceBookProvider;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Crea/aggiorna righe ProductPrice con validità temporale dal pannello Prezzo su Product.
 */
class ProductPriceTimeline
{
    public function __construct(
        private EntityManager $entityManager,
        private DefaultPriceBookProvider $defaultPriceBookProvider,
    ) {}

    public function hasPricePanelChanges(Entity $product): bool
    {
        return $product->isAttributeChanged('listPrice')
            || $product->isAttributeChanged('prezzoCodice');
    }

    public function syncFromProduct(Entity $product, string $dateStart): bool
    {
        $productId = $product->getId();

        if (!$productId) {
            return false;
        }

        $priceBook = $this->resolvePriceBook();

        if (!$priceBook) {
            throw new Error('Listino prezzi di default non configurato.');
        }

        $listPrice = $this->floatOrNull($product->get('listPrice'));
        $prezzoCodice = $this->floatOrNull($product->get('prezzoCodice'));

        if (($listPrice === null || $listPrice <= 0) && ($prezzoCodice === null || $prezzoCodice <= 0)) {
            return false;
        }

        $normalizedDateStart = $this->normalizeDate($dateStart);

        $existing = $this->findLatestActiveRow($productId, $priceBook->getId());

        if ($existing && $this->isAlreadyAligned($existing, $listPrice, $prezzoCodice, $normalizedDateStart)) {
            return false;
        }

        if ($existing && $existing->hasAttribute('dateEnd')) {
            $existing->set('dateEnd', $this->dayBefore($normalizedDateStart));
            $this->entityManager->saveEntity($existing);
        }

        $productPrice = $this->entityManager->getNewEntity('ProductPrice');
        $productPrice->set([
            'productId' => $productId,
            'priceBookId' => $priceBook->getId(),
            'status' => 'Active',
            'dateStart' => $normalizedDateStart,
        ]);

        if ($listPrice !== null && $listPrice > 0) {
            $productPrice->set('price', round($listPrice, 2));

            if ($productPrice->hasAttribute('prezzoListinoIvaEsclusa')) {
                $productPrice->set('prezzoListinoIvaEsclusa', round($listPrice, 2));
            }
        }

        if ($prezzoCodice !== null && $prezzoCodice > 0 && $productPrice->hasAttribute('prezzoCodice')) {
            $productPrice->set('prezzoCodice', round($prezzoCodice, 2));
        }

        $this->entityManager->saveEntity($productPrice);

        return true;
    }

    private function resolvePriceBook(): ?Entity
    {
        $priceBook = $this->defaultPriceBookProvider->get();

        if ($priceBook) {
            return $priceBook;
        }

        return $this->entityManager
            ->getRDBRepository('PriceBook')
            ->where([
                'name*' => 'ARIEL',
                'status' => 'Active',
            ])
            ->order('name', 'DESC')
            ->findOne();
    }

    private function findLatestActiveRow(string $productId, string $priceBookId): ?Entity
    {
        return $this->entityManager
            ->getRDBRepository('ProductPrice')
            ->where([
                'productId' => $productId,
                'priceBookId' => $priceBookId,
                'status' => 'Active',
            ])
            ->order('dateStart', 'DESC')
            ->findOne();
    }

    private function isAlreadyAligned(
        Entity $existing,
        ?float $listPrice,
        ?float $prezzoCodice,
        string $dateStart
    ): bool {
        $existingStart = substr((string) ($existing->get('dateStart') ?? ''), 0, 10);

        if ($existingStart !== $dateStart) {
            return false;
        }

        $existingList = $this->floatOrNull($existing->get('prezzoListinoIvaEsclusa'))
            ?? $this->floatOrNull($existing->get('price'));

        if (!$this->amountsEqual($existingList, $listPrice)) {
            return false;
        }

        $existingCodice = $this->floatOrNull($existing->get('prezzoCodice'));

        return $this->amountsEqual($existingCodice, $prezzoCodice);
    }

    private function amountsEqual(?float $left, ?float $right): bool
    {
        if ($left === null && $right === null) {
            return true;
        }

        if ($left === null || $right === null) {
            return false;
        }

        return abs($left - $right) < 0.009;
    }

    private function normalizeDate(string $dateStart): string
    {
        $normalized = substr(trim($dateStart), 0, 10);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized)) {
            throw new Error('Data inizio validità non valida.');
        }

        return $normalized;
    }

    private function dayBefore(string $dateStart): string
    {
        return date('Y-m-d', strtotime($dateStart . ' -1 day'));
    }

    private function floatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
