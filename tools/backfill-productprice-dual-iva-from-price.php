<?php
/**
 * Popola prezzoListinoIvaInclusa / prezzoListinoIvaEsclusa da campo price esistente.
 */

declare(strict_types=1);

$options = getopt('', [
    'crm-root::',
    'price-book-id::',
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

$sample = $entityManager->getNewEntity('ProductPrice');

if (!$sample->hasAttribute('prezzoListinoIvaInclusa')) {
    fwrite(STDERR, "ERRORE: campi dual IVA assenti su ProductPrice.\n");
    fwrite(STDERR, "Esegui: php tools/install-productprice-dual-iva-fields.php\n");
    exit(1);
}

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
$errors = 0;

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
        fwrite(STDOUT, "[dry-run] {$label}: price={$price}\n");
        $updated++;
        continue;
    }

    try {
        $fresh = $entityManager->getEntityById('ProductPrice', $productPrice->getId());

        if (!$fresh) {
            $errors++;
            continue;
        }

        $fresh->set('price', $price);
        $ivaSync->backfillProductPriceFromNativePrice($fresh);
        $entityManager->saveEntity($fresh);
        $ivaSync->syncProductFromProductPrice($fresh);

        if ($verbose || $updated < 5) {
            fwrite(STDOUT, sprintf(
                "OK %s: price=%s IVI=%s NET=%s COD=%s\n",
                $label,
                $price,
                $fresh->get('prezzoListinoIvaInclusa'),
                $fresh->get('prezzoListinoIvaEsclusa'),
                $fresh->get('prezzoCodice')
            ));
        }

        $updated++;
    } catch (Throwable $e) {
        $errors++;
        fwrite(STDERR, "ERR {$label}: {$e->getMessage()}\n");
    }
}

fwrite(STDOUT, "\nRighe aggiornate: {$updated}, saltate: {$skipped}, errori: {$errors}" . ($dryRun ? ' (dry-run)' : '') . "\n");
exit($errors > 0 ? 1 : 0);
