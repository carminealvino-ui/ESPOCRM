<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Mantiene allineato prodotticontratti con i prodotti presenti negli articoli del contratto.
 */
class ProductContrattiSync
{
    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function syncFromQuoteItems(Entity $quote): void
    {
        $quoteId = $quote->getId();

        if (!$quoteId) {
            return;
        }

        $productIds = $this->getProductIdsFromQuoteItems($quoteId);
        $linkedIds = $this->getLinkedProductIds($quote);

        $relation = $this->entityManager
            ->getRDBRepository('Quote')
            ->getRelation($quote, 'prodotti');

        $saveOptions = [
            'silent' => true,
            'skipHooks' => true,
        ];

        foreach (array_diff($productIds, $linkedIds) as $productId) {
            $relation->relateById($productId, $saveOptions);
        }

        foreach (array_diff($linkedIds, $productIds) as $productId) {
            $relation->unrelateById($productId, $saveOptions);
        }
    }

    /**
     * @return string[]
     */
    private function getProductIdsFromQuoteItems(string $quoteId): array
    {
        $collection = $this->entityManager
            ->getRDBRepository('QuoteItem')
            ->where([
                'quoteId' => $quoteId,
                'productId!=' => null,
            ])
            ->find();

        $ids = [];

        foreach ($collection as $item) {
            $productId = $item->get('productId');

            if ($productId) {
                $ids[$productId] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * @return string[]
     */
    private function getLinkedProductIds(Entity $quote): array
    {
        $collection = $this->entityManager
            ->getRDBRepository('Quote')
            ->getRelation($quote, 'prodotti')
            ->find();

        $ids = [];

        foreach ($collection as $product) {
            $ids[] = $product->getId();
        }

        return $ids;
    }
}
