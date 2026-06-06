<?php
/**
 * Backfill listPrice + prezzoCodice sulle righe itemList dei contratti esistenti.
 * Usa dateQuoted (Data Contratto) per validita listino prodotto.
 */

declare(strict_types=1);

$options = getopt('', [
    'crm-root::',
    'quote-id::',
    'dry-run',
    'verbose',
    'limit:',
]);

$crmRoot = rtrim($options['crm-root'] ?? getcwd(), '/');
$configInternal = $crmRoot . '/data/config-internal.php';

if (!is_file($configInternal)) {
    fwrite(STDERR, "config-internal.php non trovato in {$crmRoot}/data/\n");
    exit(1);
}

chdir($crmRoot);
require_once $crmRoot . '/bootstrap.php';

$app = new \Espo\Core\Application();
$app->setupSystemUser();

$entityManager = $app->getContainer()->get('entityManager');
$pricingCalculator = $app->getContainer()->get(\Espo\Custom\Services\QuotePricingCalculator::class);

$dryRun = array_key_exists('dry-run', $options);
$verbose = array_key_exists('verbose', $options);
$limit = isset($options['limit']) ? (int) $options['limit'] : 0;
$quoteIdFilter = $options['quote-id'] ?? null;

$repository = $entityManager->getRDBRepository('Quote');

if ($quoteIdFilter) {
    $quote = $entityManager->getEntityById('Quote', $quoteIdFilter);

    if (!$quote) {
        fwrite(STDERR, "Contratto non trovato: {$quoteIdFilter}\n");
        exit(1);
    }

    $collection = [$quote];
} else {
    $collection = $repository
        ->where(['itemList!=' => null])
        ->find();
}

$updated = 0;
$skipped = 0;
$errors = 0;
$processed = 0;

foreach ($collection as $quote) {
    if ($limit > 0 && $processed >= $limit) {
        break;
    }

    $processed++;

    $itemList = $quote->get('itemList');

    if (!is_array($itemList) || $itemList === []) {
        $skipped++;

        continue;
    }

    $changed = false;

    foreach ($itemList as $index => $item) {
        $productId = is_array($item) ? ($item['productId'] ?? null) : ($item->productId ?? null);

        if (!$productId) {
            continue;
        }

        $product = $entityManager->getEntityById('Product', $productId);

        if (!$product) {
            continue;
        }

        $prices = $pricingCalculator->resolveItemCatalogPricesForProduct($quote, $product);

        if (is_array($item)) {
            if ($prices['listPrice'] !== null && $prices['listPrice'] > 0) {
                $itemList[$index]['listPrice'] = $prices['listPrice'];
                $changed = true;
            }

            if ($prices['prezzoCodice'] !== null && $prices['prezzoCodice'] > 0) {
                $itemList[$index]['prezzoCodice'] = $prices['prezzoCodice'];
                $changed = true;
            }
        }
    }

    if (!$changed) {
        $skipped++;

        continue;
    }

    if ($dryRun) {
        if ($verbose) {
            echo '[dry-run] ' . $quote->getId() . ' ' . ($quote->get('name') ?? '') . PHP_EOL;
        }

        $updated++;

        continue;
    }

    try {
        $quote->set('itemList', $itemList);
        $pricingCalculator->syncOnBeforeSave($quote);
        $entityManager->saveEntity($quote, ['silent' => true]);

        if ($verbose) {
            echo 'OK ' . $quote->getId() . ' dateQuoted=' . ($quote->get('dateQuoted') ?? 'null') . PHP_EOL;
        }

        $updated++;
    } catch (\Throwable $e) {
        $errors++;
        fwrite(STDERR, 'ERRORE ' . $quote->getId() . ': ' . $e->getMessage() . PHP_EOL);
    }
}

echo "Contratti aggiornati: {$updated}, saltati: {$skipped}, errori: {$errors}" . PHP_EOL;
