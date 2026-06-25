<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use PDO;

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
        $linkedIds = $this->getLinkedProductIds($quoteId);

        foreach (array_diff($productIds, $linkedIds) as $productId) {
            $this->ensureLink($productId, $quoteId);
        }

        foreach (array_diff($linkedIds, $productIds) as $productId) {
            $this->removeLink($productId, $quoteId);
        }
    }

    /**
     * @return string[]
     */
    private function getProductIdsFromQuoteItems(string $quoteId): array
    {
        $pdo = $this->entityManager->getPDO();

        $stmt = $pdo->prepare(
            'SELECT DISTINCT COALESCE(qi.product_id, p.id) AS product_id
             FROM quote_item qi
             LEFT JOIN product p ON p.deleted = 0 AND p.name = qi.name
             WHERE qi.quote_id = ?
               AND qi.deleted = 0
               AND (qi.product_id IS NOT NULL OR p.id IS NOT NULL)'
        );
        $stmt->execute([$quoteId]);

        $ids = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $productId = $row['product_id'] ?? null;

            if ($productId) {
                $ids[$productId] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * @return string[]
     */
    private function getLinkedProductIds(string $quoteId): array
    {
        $pdo = $this->entityManager->getPDO();

        $stmt = $pdo->prepare(
            'SELECT product_id
             FROM prodotticontratti
             WHERE quote_id = ? AND deleted = 0 AND product_id IS NOT NULL'
        );
        $stmt->execute([$quoteId]);

        $ids = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $productId = $row['product_id'] ?? null;

            if ($productId) {
                $ids[] = $productId;
            }
        }

        return $ids;
    }

    private function ensureLink(string $productId, string $quoteId): void
    {
        $pdo = $this->entityManager->getPDO();

        $stmt = $pdo->prepare(
            'SELECT id, deleted
             FROM prodotticontratti
             WHERE product_id = ? AND quote_id = ?
             LIMIT 1'
        );
        $stmt->execute([$productId, $quoteId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            if ((int) ($row['deleted'] ?? 0) === 1) {
                $pdo->prepare('UPDATE prodotticontratti SET deleted = 0 WHERE id = ?')
                    ->execute([$row['id']]);
            }

            return;
        }

        $pdo->prepare(
            'INSERT INTO prodotticontratti (product_id, quote_id, deleted) VALUES (?, ?, 0)'
        )->execute([$productId, $quoteId]);
    }

    private function removeLink(string $productId, string $quoteId): void
    {
        $pdo = $this->entityManager->getPDO();

        $pdo->prepare(
            'UPDATE prodotticontratti
             SET deleted = 1
             WHERE product_id = ? AND quote_id = ? AND deleted = 0'
        )->execute([$productId, $quoteId]);
    }
}
