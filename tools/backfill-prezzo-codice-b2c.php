<?php
/**
 * Bonifica Prezzo Codice su contratti B2C (IVA inclusa).
 * Ricalcola listino/codice in riga + totali (evita 4090.91 al posto di 4500).
 *
 *   php tools/backfill-prezzo-codice-b2c.php --dry-run
 *   php tools/backfill-prezzo-codice-b2c.php --dry-run --limit=20
 *   php tools/backfill-prezzo-codice-b2c.php --apply
 *   php tools/backfill-prezzo-codice-b2c.php --apply --quote-id=XXXX
 */

declare(strict_types=1);

$options = getopt('', [
    'crm-root::',
    'quote-id::',
    'dry-run',
    'apply',
    'limit:',
    'verbose',
]);

$crmRoot = rtrim($options['crm-root'] ?? dirname(__DIR__), '/');
$apply = array_key_exists('apply', $options);
$dryRun = !$apply || array_key_exists('dry-run', $options);

if (array_key_exists('apply', $options) && !array_key_exists('dry-run', $options)) {
    $dryRun = false;
}

$verbose = array_key_exists('verbose', $options) || $dryRun;
$limit = isset($options['limit']) ? max(1, (int) $options['limit']) : 0;
$quoteIdFilter = $options['quote-id'] ?? null;

chdir($crmRoot);
require_once $crmRoot . '/bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\QuotePricingCalculator;

$app = new Application();
$app->setupSystemUser();

$em = $app->getContainer()->get('entityManager');
/** @var QuotePricingCalculator $calc */
$calc = $app->getContainer()->get('injectableFactory')->create(QuotePricingCalculator::class);

echo "=== Bonifica Prezzo Codice B2C (IVA inclusa) ===\n";
echo 'Modalità: ' . ($dryRun ? 'DRY-RUN' : 'APPLICA') . "\n\n";

if ($quoteIdFilter) {
    $quote = $em->getEntityById('Quote', $quoteIdFilter);

    if (!$quote) {
        fwrite(STDERR, "Contratto non trovato: {$quoteIdFilter}\n");
        exit(1);
    }

    $collection = [$quote];
} else {
    $collection = $em->getRDBRepository('Quote')
        ->where([
            'isTaxInclusive' => true,
            'itemList!=' => null,
        ])
        ->order('modifiedAt', 'DESC')
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

    if (!$calc->isQuotePricesTaxInclusive($quote)) {
        $skipped++;
        continue;
    }

    $before = [];
    $changed = false;

    foreach ($itemList as $index => $item) {
        $productId = is_array($item) ? ($item['productId'] ?? null) : null;

        if (!$productId) {
            continue;
        }

        $product = $em->getEntityById('Product', $productId);

        if (!$product) {
            continue;
        }

        $oldCodice = is_array($item) ? ($item['prezzoCodice'] ?? null) : null;
        $prices = $calc->resolveItemCatalogPricesForProduct($quote, $product);
        $newCodice = $prices['prezzoCodice'] ?? null;
        $newList = $prices['listPrice'] ?? null;

        if ($newList !== null && $newList > 0 && is_array($item)) {
            if (!isset($item['listPrice']) || abs((float) $item['listPrice'] - $newList) > 0.02) {
                $itemList[$index]['listPrice'] = $newList;
                $changed = true;
            }
        }

        if ($newCodice !== null && $newCodice > 0 && is_array($item)) {
            if ($oldCodice === null || abs((float) $oldCodice - $newCodice) > 0.02) {
                $before[] = sprintf(
                    'riga%d: %s → %s',
                    $index,
                    $oldCodice === null ? 'null' : (string) $oldCodice,
                    (string) $newCodice
                );
                $itemList[$index]['prezzoCodice'] = $newCodice;
                $changed = true;
            }
        }
    }

    if (!$changed) {
        $skipped++;
        continue;
    }

    $label = ($quote->get('name') ?: $quote->getId()) . ' | ' . implode('; ', $before);

    if ($dryRun) {
        echo "[DRY] {$label}\n";
        $updated++;
        continue;
    }

    try {
        $quote->set('itemList', $itemList);
        $calc->syncOnBeforeSave($quote);
        $em->saveEntity($quote, [
            'silent' => true,
            'skipHooks' => false,
        ]);
        echo "[OK] {$label}\n";
        $updated++;
    } catch (Throwable $e) {
        $errors++;
        fwrite(STDERR, '[ERR] ' . $quote->getId() . ': ' . $e->getMessage() . "\n");
    }
}

echo "\nElaborati: {$processed}, da aggiornare/aggiornati: {$updated}, saltati: {$skipped}, errori: {$errors}\n";

if ($dryRun) {
    echo "Per applicare: php tools/backfill-prezzo-codice-b2c.php --apply\n";
}
