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
        return $product->isAttributeChanged('prezzoListinoIvaEsclusa')
            || $product->isAttributeChanged('prezzoListinoIvaInclusa')
            || $product->isAttributeChanged('prezzoCodice')
            || $product->isAttributeChanged('prezzoCodiceIvaInclusa');
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

        $listinoNet = $this->floatOrNull($product->get('prezzoListinoIvaEsclusa'));
        $listinoIvi = $this->floatOrNull($product->get('prezzoListinoIvaInclusa'));
        $codiceNet = $this->floatOrNull($product->get('prezzoCodice'));
        $codiceIvi = $this->floatOrNull($product->get('prezzoCodiceIvaInclusa'));

        if (!$this->hasAnyPrice($listinoNet, $listinoIvi, $codiceNet, $codiceIvi)) {
            return false;
        }

        $normalizedDateStart = $this->normalizeDate($dateStart);

        $existing = $this->findLatestActiveRow($productId, $priceBook->getId());

        if ($existing && $this->isAlreadyAligned(
            $existing,
            $listinoNet,
            $listinoIvi,
            $codiceNet,
            $codiceIvi,
            $normalizedDateStart
        )) {
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

        $this->applyListino($productPrice, $priceBook, $listinoNet, $listinoIvi);
        $this->applyCodice($productPrice, $codiceNet, $codiceIvi);

        $this->entityManager->saveEntity($productPrice);

        return true;
    }

    private function applyListino(
        Entity $productPrice,
        Entity $priceBook,
        ?float $listinoNet,
        ?float $listinoIvi
    ): void {
        if ($listinoNet !== null && $listinoNet > 0 && $productPrice->hasAttribute('prezzoListinoIvaEsclusa')) {
            $productPrice->set('prezzoListinoIvaEsclusa', round($listinoNet, 2));
        }

        if ($listinoIvi !== null && $listinoIvi > 0 && $productPrice->hasAttribute('prezzoListinoIvaInclusa')) {
            $productPrice->set('prezzoListinoIvaInclusa', round($listinoIvi, 2));
        }

        $taxInclusive = (bool) $priceBook->get('isTaxInclusive');

        if ($taxInclusive && $listinoIvi !== null && $listinoIvi > 0) {
            $productPrice->set('price', round($listinoIvi, 2));

            return;
        }

        if ($listinoNet !== null && $listinoNet > 0) {
            $productPrice->set('price', round($listinoNet, 2));
        }
    }

    private function applyCodice(
        Entity $productPrice,
        ?float $codiceNet,
        ?float $codiceIvi
    ): void {
        if ($codiceNet !== null && $codiceNet > 0 && $productPrice->hasAttribute('prezzoCodice')) {
            $productPrice->set('prezzoCodice', round($codiceNet, 2));
        }

        if ($codiceIvi !== null && $codiceIvi > 0 && $productPrice->hasAttribute('prezzoCodiceIvaInclusa')) {
            $productPrice->set('prezzoCodiceIvaInclusa', round($codiceIvi, 2));
        }
    }

    private function hasAnyPrice(?float $listinoNet, ?float $listinoIvi, ?float $codiceNet, ?float $codiceIvi): bool
    {
        foreach ([$listinoNet, $listinoIvi, $codiceNet, $codiceIvi] as $value) {
            if ($value !== null && $value > 0) {
                return true;
            }
        }

        return false;
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
        ?float $listinoNet,
        ?float $listinoIvi,
        ?float $codiceNet,
        ?float $codiceIvi,
        string $dateStart
    ): bool {
        $existingStart = substr((string) ($existing->get('dateStart') ?? ''), 0, 10);

        if ($existingStart !== $dateStart) {
            return false;
        }

        $existingListNet = $this->floatOrNull($existing->get('prezzoListinoIvaEsclusa'))
            ?? $this->floatOrNull($existing->get('price'));
        $existingListIvi = $this->floatOrNull($existing->get('prezzoListinoIvaInclusa'));
        $existingCodiceNet = $this->floatOrNull($existing->get('prezzoCodice'));
        $existingCodiceIvi = $this->floatOrNull($existing->get('prezzoCodiceIvaInclusa'));

        return $this->amountsEqual($existingListNet, $listinoNet)
            && $this->amountsEqual($existingListIvi, $listinoIvi)
            && $this->amountsEqual($existingCodiceNet, $codiceNet)
            && $this->amountsEqual($existingCodiceIvi, $codiceIvi);
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
