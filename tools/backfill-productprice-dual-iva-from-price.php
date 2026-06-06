<?php
/**
 * Popola prezzoListinoIvaInclusa / prezzoListinoIvaEsclusa da campo price esistente.
 *
 *   cd ~/public_html/crm/mec-group
 *   php tools/backfill-productprice-dual-iva-from-price.php --dry-run
 *   php tools/backfill-productprice-dual-iva-from-price.php --price-book-name='ARIEL'
 */

declare(strict_types=1);

$options = getopt('', [
    'crm-root::',
    'price-book-id::',
    'price-book-name::',
    'dry-run',
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

$where = [];

if (!empty($options['price-book-id'])) {
    $where['priceBookId'] = $options['price-book-id'];
}

$collection = $entityManager
    ->getRDBRepository('ProductPrice')
    ->where($where)
    ->find();

$priceBookFilter = $options['price-book-name'] ?? null;
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

    if ($price <= 0) {
        $skipped++;
        continue;
    }

    $ivi = (float) ($productPrice->get('prezzoListinoIvaInclusa') ?? 0);
    $net = (float) ($productPrice->get('prezzoListinoIvaEsclusa') ?? 0);

    if ($ivi > 0 && $net > 0) {
        $skipped++;
        continue;
    }

    $label = $productPrice->get('name') ?: $productPrice->getId();

    if ($dryRun) {
        fwrite(STDOUT, "[dry-run] {$label}: price={$price} → ricalcolo dual IVA\n");
        $updated++;
        continue;
    }

    $productPrice->set('price', $price);
    $ivaSync->syncProductPriceOnBeforeSave($productPrice);
    $entityManager->saveEntity($productPrice, ['silent' => true]);
    $ivaSync->syncProductFromProductPrice($productPrice);

    fwrite(STDOUT, "OK {$label}: IVI={$productPrice->get('prezzoListinoIvaInclusa')} NET={$productPrice->get('prezzoListinoIvaEsclusa')}\n");
    $updated++;
}

fwrite(STDOUT, "\nRighe aggiornate: {$updated}, saltate: {$skipped}" . ($dryRun ? ' (dry-run)' : '') . "\n");
