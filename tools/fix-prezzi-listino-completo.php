<?php
/**
 * Fix prezzi dual IVA listino — SQL diretto (no dipendenza container IvaDualPriceSync).
 *
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
    fwrite(STDERR, "Eseguire dalla root CRM.\n");
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
$priceBookFilter = isset($options['price-book-name']) ? (string) $options['price-book-name'] : null;
$priceBookIdFilter = $options['price-book-id'] ?? null;

out('=== FIX prezzi listino dual IVA (sql-20260606) ===');

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

if ($missingColumns !== [] || $forceRebuild) {
    $entityDefsPath = $crmRoot . '/custom/Espo/Custom/Resources/metadata/entityDefs/ProductPrice.json';
    if (!is_file($entityDefsPath)) {
        fwrite(STDERR, "ERRORE: manca {$entityDefsPath}\n");
        exit(1);
    }
    if ($dryRun) {
        out('[dry-run] Eseguirebbe rebuild');
    } else {
        out('Rebuild schema...');
        $dataManager->rebuild();
        out('Rebuild completato.');
    }
}

$filterSql = '';
$filterParams = [];

if ($priceBookIdFilter) {
    $filterSql = ' AND pp.price_book_id = ?';
    $filterParams[] = $priceBookIdFilter;
} elseif ($priceBookFilter) {
    $filterSql = ' AND pb.name LIKE ?';
    $filterParams[] = '%' . $priceBookFilter . '%';
}

$diag = $pdo->prepare(
    'SELECT COUNT(*) AS tot,
            SUM(CASE WHEN pp.price > 0 THEN 1 ELSE 0 END) AS con_price,
            SUM(CASE WHEN pp.prezzo_listino_iva_inclusa > 0 THEN 1 ELSE 0 END) AS con_ivi
     FROM product_price pp
     INNER JOIN price_book pb ON pb.id = pp.price_book_id AND pb.deleted = 0
     WHERE pp.deleted = 0' . $filterSql
);
$diag->execute($filterParams);
$d = $diag->fetch(PDO::FETCH_ASSOC);
out(sprintf(
    'Diagnostica DB: tot=%s con price>0=%s con prezzo_listino_iva_inclusa>0=%s',
    $d['tot'] ?? '0',
    $d['con_price'] ?? '0',
    $d['con_ivi'] ?? '0'
));

// Aliquota: tax_code.rate (es. 10.000) oppure 10
$aliquotaExpr = 'COALESCE(NULLIF(tc.rate, 0), 10)';

$listinoSql = "
    UPDATE product_price pp
    INNER JOIN price_book pb ON pb.id = pp.price_book_id AND pb.deleted = 0
    LEFT JOIN tax_code tc ON tc.id = pb.tax_code_id AND tc.deleted = 0
    SET
        pp.aliquota_iva = {$aliquotaExpr},
        pp.prezzo_listino_iva_inclusa = CASE
            WHEN pb.is_tax_inclusive = 1 THEN pp.price
            ELSE ROUND(pp.price * (1 + {$aliquotaExpr} / 100), 2)
        END,
        pp.prezzo_listino_iva_esclusa = CASE
            WHEN pb.is_tax_inclusive = 1 THEN ROUND(pp.price / (1 + {$aliquotaExpr} / 100), 2)
            ELSE pp.price
        END
    WHERE pp.deleted = 0
      AND pp.price > 0
      AND (
          pp.prezzo_listino_iva_inclusa IS NULL OR pp.prezzo_listino_iva_inclusa = 0
          OR pp.prezzo_listino_iva_esclusa IS NULL OR pp.prezzo_listino_iva_esclusa = 0
      )
      {$filterSql}
";

$codiceSql = "
    UPDATE product_price pp
    INNER JOIN product p ON p.id = pp.product_id AND p.deleted = 0
    INNER JOIN price_book pb ON pb.id = pp.price_book_id AND pb.deleted = 0
    LEFT JOIN tax_code tc ON tc.id = pb.tax_code_id AND tc.deleted = 0
    SET
        pp.prezzo_codice = p.prezzo_codice,
        pp.prezzo_codice_iva_inclusa = ROUND(p.prezzo_codice * (1 + {$aliquotaExpr} / 100), 2)
    WHERE pp.deleted = 0
      AND p.prezzo_codice > 0
      AND (
          pp.prezzo_codice IS NULL OR pp.prezzo_codice = 0
          OR pp.prezzo_codice_iva_inclusa IS NULL OR pp.prezzo_codice_iva_inclusa = 0
      )
      {$filterSql}
";

$productSql = "
    UPDATE product p
    INNER JOIN product_price pp ON pp.product_id = p.id AND pp.deleted = 0
    INNER JOIN price_book pb ON pb.id = pp.price_book_id AND pb.deleted = 0
    SET
        p.list_price = pp.prezzo_listino_iva_esclusa,
        p.unit_price = pp.prezzo_listino_iva_esclusa
    WHERE p.deleted = 0
      AND pp.prezzo_listino_iva_esclusa > 0
      {$filterSql}
";

if ($dryRun) {
    $countStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM product_price pp
         INNER JOIN price_book pb ON pb.id = pp.price_book_id AND pb.deleted = 0
         WHERE pp.deleted = 0 AND pp.price > 0
           AND (pp.prezzo_listino_iva_inclusa IS NULL OR pp.prezzo_listino_iva_inclusa = 0)' . $filterSql
    );
    $countStmt->execute($filterParams);
    out('[dry-run] Righe listino da aggiornare: ' . $countStmt->fetchColumn());
    exit(0);
}

$listinoStmt = $pdo->prepare($listinoSql);
$listinoStmt->execute($filterParams);
$listinoRows = $listinoStmt->rowCount();
out("Aggiornate righe listino (IVA): {$listinoRows}");

$codiceStmt = $pdo->prepare($codiceSql);
$codiceStmt->execute($filterParams);
out('Aggiornate righe prezzo codice: ' . $codiceStmt->rowCount());

if (columnExists($pdo, 'product', 'prezzo_listino_iva_inclusa')) {
    $productIviSql = "
        UPDATE product p
        INNER JOIN product_price pp ON pp.product_id = p.id AND pp.deleted = 0
        INNER JOIN price_book pb ON pb.id = pp.price_book_id AND pb.deleted = 0
        SET p.prezzo_listino_iva_inclusa = pp.prezzo_listino_iva_inclusa
        WHERE p.deleted = 0 AND pp.prezzo_listino_iva_inclusa > 0 {$filterSql}
    ";
    $st = $pdo->prepare($productIviSql);
    $st->execute($filterParams);
    out('Sync product.prezzo_listino_iva_inclusa: ' . $st->rowCount());
}

$productStmt = $pdo->prepare($productSql);
$productStmt->execute($filterParams);
out('Sync product.list_price: ' . $productStmt->rowCount());

$diag->execute($filterParams);
$d2 = $diag->fetch(PDO::FETCH_ASSOC);
out(sprintf(
    'Dopo fix: con prezzo_listino_iva_inclusa>0=%s',
    $d2['con_ivi'] ?? '0'
));

out('Fatto. Ctrl+F5 su Listino ARIEL Energia.');

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);

    return (int) $stmt->fetchColumn() > 0;
}
