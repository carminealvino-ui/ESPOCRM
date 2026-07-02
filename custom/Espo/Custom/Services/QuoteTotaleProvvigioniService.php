<?php

namespace Espo\Custom\Services;

use Espo\ORM\EntityManager;
use PDO;

/**
 * Aggiorna totaleProvvigioni sul contratto dalla somma delle righe Provvigione.
 */
class QuoteTotaleProvvigioniService
{
    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function syncForQuoteId(string $quoteId): void
    {
        $pdo = $this->entityManager->getPDO();

        $stmt = $pdo->prepare(
            'SELECT COALESCE(SUM(importo), 0) AS totale
             FROM provvigione
             WHERE deleted = 0 AND contratto_id = :quoteId'
        );
        $stmt->execute(['quoteId' => $quoteId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return;
        }

        $totale = round((float) $row['totale'], 2);

        $check = $pdo->prepare(
            'SELECT totale_provvigioni
             FROM quote
             WHERE id = :quoteId AND deleted = 0'
        );
        $check->execute(['quoteId' => $quoteId]);
        $current = $check->fetch(PDO::FETCH_ASSOC);

        if ($current === false) {
            return;
        }

        if (round((float) ($current['totale_provvigioni'] ?? 0), 2) === $totale) {
            return;
        }

        $update = $pdo->prepare(
            'UPDATE quote
             SET totale_provvigioni = :totale
             WHERE id = :quoteId AND deleted = 0'
        );
        $update->execute([
            'totale' => $totale,
            'quoteId' => $quoteId,
        ]);
    }
}
