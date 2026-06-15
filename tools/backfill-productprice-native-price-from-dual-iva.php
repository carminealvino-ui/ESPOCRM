<?php
/**
 * Allinea campo nativo price (Sales Pack) da prezzoListino IVA inclusa/esclusa quando price e vuoto.
 */

declare(strict_types=1);

$options = getopt('', [
    'crm-root::',
    'price-book-name::',
    'dry-run',
    'verbose',
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
$ivaSync = $app->getContainer()->get(\Espo\Custom\Services\IvaDualPriceSync::class);

$dryRun = array_key_exists('dry-run', $options);
$verbose = array_key_exists('verbose', $options);
$priceBookFilter = $options['price-book-name'] ?? null;

$collection = $entityManager->getRDBRepository('ProductPrice')->find();

$updated = 0;
$skipped = 0;

foreach ($collection as $productPrice) {
    if ($priceBookFilter) {
        $priceBook = $entityManager->getEntityById('PriceBook', $productPrice->get('priceBookId'));

        if (!$priceBook || stripos((string) $priceBook->get('name'), $priceBookFilter) === false) {
            continue;
        }
    }

    $price = (float) ($productPrice->get('price') ?? 0);

    if ($price > 0) {
        $skipped++;

        continue;
    }

    $ivi = (float) ($productPrice->get('prezzoListinoIvaInclusa') ?? 0);
    $net = (float) ($productPrice->get('prezzoListinoIvaEsclusa') ?? 0);

    if ($ivi <= 0 && $net <= 0) {
        $skipped++;

        continue;
    }

    if ($dryRun) {
        echo '[dry-run] ' . $productPrice->getId()
            . ' price <- IVI ' . $ivi . ' / net ' . $net . PHP_EOL;
        $updated++;

        continue;
    }

    $ivaSync->syncProductPriceOnBeforeSave($productPrice);
    $entityManager->saveEntity($productPrice, ['silent' => true, 'skipHooks' => false]);
    $ivaSync->syncProductFromProductPrice($productPrice);

    if ($verbose) {
        echo 'OK ' . $productPrice->getId()
            . ' price=' . $productPrice->get('price') . PHP_EOL;
    }

    $updated++;
}

echo "ProductPrice aggiornati: {$updated}, saltati: {$skipped}" . PHP_EOL;
