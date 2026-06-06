<?php
/**
 * Fix completo prezzi dual IVA listino (diagnostica + schema + backfill).
 * Un solo file — niente dipendenze da script bash aggiornati.
 *
 *   cd ~/public_html/crm/mec-group
 *   php tools/fix-prezzi-listino-completo.php
 *   php tools/fix-prezzi-listino-completo.php --price-book-name=ARIEL --dry-run
 */

declare(strict_types=1);

$options = getopt('', ['crm-root::', 'price-book-id::', 'price-book-name::', 'dry-run', 'force-rebuild']);

function out(string $msg): void
{
    fwrite(STDOUT, $msg . PHP_EOL);
}

$crmRoot = rtrim($options['crm-root'] ?? getcwd(), '/');
$configInternal = $crmRoot . '/data/config-internal.php';

if (!is_file($configInternal)) {
    fwrite(STDERR, "Eseguire dalla root CRM (data/config-internal.php).\n");
    exit(1);
}

chdir($crmRoot);
require_once $crmRoot . '/bootstrap.php';

$app = new \Espo\Core\Application();
$app->setupSystemUser();

$container = $app->getContainer();
$entityManager = $container->get('entityManager');
$dataManager = $container->get('dataManager');
$pdo = $entityManager->getPDO();

$dryRun = array_key_exists('dry-run', $options);
$forceRebuild = array_key_exists('force-rebuild', $options);
$priceBookFilter = $options['price-book-name'] ?? null;
$priceBookIdFilter = $options['price-book-id'] ?? null;

out('=== FIX prezzi listino dual IVA ===');

$requiredColumns = [
    'prezzo_listino_iva_inclusa',
    'prezzo_listino_iva_esclusa',
    'prezzo_codice',
    'prezzo_codice_iva_inclusa',
    'aliquota_iva',
];

$missingColumns = [];

foreach ($requiredColumns as $col) {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute(['product_price', $col]);
    if ((int) $stmt->fetchColumn() === 0) {
        $missingColumns[] = $col;
    }
}

out('Colonne product_price mancanti: ' . ($missingColumns === [] ? 'nessuna' : implode(', ', $missingColumns)));

$entityDefsPath = $crmRoot . '/custom/Espo/Custom/Resources/metadata/entityDefs/ProductPrice.json';

if ($missingColumns !== [] || $forceRebuild) {
    if (!is_file($entityDefsPath)) {
        fwrite(STDERR, "ERRORE: manca {$entityDefsPath} — eseguire deploy listino dual IVA.\n");
        exit(1);
    }
    if ($dryRun) {
        out('[dry-run] Eseguirebbe php command.php rebuild');
    } else {
        out('Rebuild schema...');
        $dataManager->rebuild();
        foreach (['data/cache', 'data/tmp'] as $dir) {
            $path = $crmRoot . '/' . $dir;
            if (!is_dir($path)) {
                continue;
            }
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $file) {
                $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
            }
        }
        out('Rebuild completato.');
    }
}

// Diagnostica SQL
$sql = 'SELECT COUNT(*) AS tot,
        SUM(CASE WHEN price IS NOT NULL AND price > 0 THEN 1 ELSE 0 END) AS con_price,
        SUM(CASE WHEN prezzo_listino_iva_inclusa IS NOT NULL AND prezzo_listino_iva_inclusa > 0 THEN 1 ELSE 0 END) AS con_ivi
        FROM product_price WHERE deleted = 0';
$params = [];

if ($priceBookIdFilter) {
    $sql .= ' AND price_book_id = ?';
    $params[] = $priceBookIdFilter;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
out(sprintf(
    'Diagnostica DB: tot=%s con price>0=%s con prezzo_listino_iva_inclusa>0=%s',
    $row['tot'] ?? '?',
    $row['con_price'] ?? '?',
    $row['con_ivi'] ?? '?'
));

try {
    $ivaSync = $container->get(\Espo\Custom\Services\IvaDualPriceSync::class);
} catch (Throwable $e) {
    fwrite(STDERR, 'ERRORE IvaDualPriceSync: ' . $e->getMessage() . "\n");
    exit(1);
}

$where = ['deleted' => false];

if ($priceBookIdFilter) {
    $where['priceBookId'] = $priceBookIdFilter;
}

$collection = $entityManager->getRDBRepository('ProductPrice')->where($where)->find();

$updated = 0;
$skipped = 0;
$errors = 0;

foreach ($collection as $productPrice) {
    if ($priceBookFilter) {
        $pb = $entityManager->getEntityById('PriceBook', $productPrice->get('priceBookId'));
        if (!$pb || stripos((string) $pb->get('name'), $priceBookFilter) === false) {
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

    $label = (string) ($productPrice->get('name') ?: $productPrice->getId());

    if ($dryRun) {
        out("[dry-run] {$label} price={$price}");
        $updated++;
        continue;
    }

    try {
        $fresh = $entityManager->getEntityById('ProductPrice', $productPrice->getId());
        if (!$fresh) {
            $errors++;
            continue;
        }

        if (!$fresh->hasAttribute('prezzoListinoIvaInclusa')) {
            // Fallback SQL se metadata non ancora attiva
            $pb = $entityManager->getEntityById('PriceBook', $fresh->get('priceBookId'));
            $aliquota = 10.0;
            if ($pb && $pb->get('taxCodeId')) {
                $tc = $entityManager->getEntityById('TaxCode', $pb->get('taxCodeId'));
                if ($tc && is_numeric($tc->get('rate'))) {
                    $aliquota = (float) $tc->get('rate');
                }
            }
            $taxInclusive = $pb && (bool) $pb->get('isTaxInclusive');
            $listinoIvi = $taxInclusive ? $price : round($price * (1 + $aliquota / 100), 2);
            $listinoNet = $taxInclusive ? round($price / (1 + $aliquota / 100), 2) : $price;

            $upd = $pdo->prepare(
                'UPDATE product_price SET
                    prezzo_listino_iva_inclusa = ?,
                    prezzo_listino_iva_esclusa = ?,
                    aliquota_iva = ?
                 WHERE id = ? AND deleted = 0'
            );
            $upd->execute([$listinoIvi, $listinoNet, $aliquota, $fresh->getId()]);
            out("OK (SQL) {$label}: IVI={$listinoIvi} NET={$listinoNet}");
            $updated++;
            continue;
        }

        $fresh->set('price', $price);
        $ivaSync->backfillProductPriceFromNativePrice($fresh);
        $entityManager->saveEntity($fresh);
        $ivaSync->syncProductFromProductPrice($fresh);

        if ($updated < 8) {
            out(sprintf(
                'OK %s: IVI=%s NET=%s COD=%s',
                $label,
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

out(sprintf("\nFine: aggiornate=%d saltate=%d errori=%d%s", $updated, $skipped, $errors, $dryRun ? ' (dry-run)' : ''));

if (!$dryRun && $updated > 0) {
    out('Esegui Ctrl+F5 su Listino ARIEL Energia.');
}

exit($errors > 0 ? 1 : 0);
