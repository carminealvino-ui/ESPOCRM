#!/usr/bin/env php
<?php
/**
 * Bonifica itemList: rimuove productId/productName se il Product non esiste più.
 *
 * Uso (dalla root CRM, es. ~/public_html/crm/mec-group):
 *   php tools/bonifica-quote-item-product-orphan.php --dry-run --quote-id=6a36664f86ef51ce7
 *   php tools/bonifica-quote-item-product-orphan.php --quote-id=6a36664f86ef51ce7
 */
declare(strict_types=1);

$crmRoot = getenv('CRM_ROOT') ?: (getenv('HOME') . '/public_html/crm/mec-group');

if (!is_dir($crmRoot)) {
    $crmRoot = dirname(__DIR__);
}

require_once $crmRoot . '/bootstrap.php';

use Espo\Core\Application;
use Espo\ORM\EntityManager;

$dryRun = in_array('--dry-run', $argv, true);
$quoteId = null;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--quote-id=')) {
        $quoteId = substr($arg, strlen('--quote-id='));
    }
}

$app = new Application();
$app->setupSystemUser();

$entityManager = $app->getContainer()->getByClass(EntityManager::class);

$repository = $entityManager->getRDBRepository('Quote');

if ($quoteId) {
    $quote = $entityManager->getEntityById('Quote', $quoteId);

    if (!$quote) {
        fwrite(STDERR, "Quote non trovato: {$quoteId}\n");
        exit(1);
    }

    $quotes = [$quote];
} else {
    $quotes = $repository->find();
}

$fixed = 0;
$rows = 0;

foreach ($quotes as $quote) {
    $itemList = $quote->get('itemList');

    if (!is_array($itemList) || $itemList === []) {
        continue;
    }

    $changed = false;

    foreach ($itemList as $index => $item) {
        $productId = is_object($item) ? ($item->productId ?? null) : ($item['productId'] ?? null);

        if (!$productId) {
            continue;
        }

        $product = $entityManager->getEntityById('Product', $productId);

        if ($product) {
            continue;
        }

        $rows++;

        if (is_object($item)) {
            $item->productId = null;
            $item->productName = null;
        } else {
            $itemList[$index]['productId'] = null;
            $itemList[$index]['productName'] = null;
        }

        $changed = true;

        echo sprintf(
            "[%s] %s — riga %d: rimosso productId orfano %s\n",
            $quote->getId(),
            $quote->get('name'),
            $index + 1,
            $productId
        );
    }

    if (!$changed) {
        continue;
    }

    $fixed++;

    if ($dryRun) {
        echo "  (dry-run: non salvato)\n";

        continue;
    }

    $quote->set('itemList', $itemList);
    $entityManager->saveEntity($quote, ['skipHooks' => true, 'silent' => true]);
    echo "  → salvato\n";
}

echo "\nContratti toccati: {$fixed}, righe orfane: {$rows}\n";

if ($dryRun) {
    echo "Esegui senza --dry-run per applicare.\n";
}
