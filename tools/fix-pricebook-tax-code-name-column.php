<?php
/**
 * Ripara colonna price_book.tax_code_name (errore ProductPrice read / modifica riga listino).
 *
 *   php tools/fix-pricebook-tax-code-name-column.php
 */

declare(strict_types=1);

$crmRoot = rtrim(getcwd(), '/');
if (!is_file($crmRoot . '/data/config-internal.php')) {
    fwrite(STDERR, "Eseguire dalla root CRM.\n");
    exit(1);
}

require_once $crmRoot . '/bootstrap.php';

$app = new \Espo\Core\Application();
$app->setupSystemUser();

$pdo = $app->getContainer()->get('entityManager')->getPDO();
$dataManager = $app->getContainer()->get('dataManager');

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);

    return (int) $stmt->fetchColumn() > 0;
}

fwrite(STDOUT, "=== Fix price_book.tax_code_name ===\n");

if (!columnExists($pdo, 'price_book', 'tax_code_id')) {
    fwrite(STDERR, "ERRORE: manca tax_code_id su price_book. Esegui install-pricebook-tax-code-field.php\n");
    exit(1);
}

if (!columnExists($pdo, 'price_book', 'tax_code_name')) {
    fwrite(STDOUT, "Colonna tax_code_name assente → rebuild...\n");
    $dataManager->rebuild();
}

if (!columnExists($pdo, 'price_book', 'tax_code_name')) {
    fwrite(STDOUT, "Aggiunta colonna tax_code_name (VARCHAR 100)...\n");
    $pdo->exec('ALTER TABLE price_book ADD COLUMN tax_code_name VARCHAR(100) DEFAULT NULL AFTER tax_code_id');
}

if (!columnExists($pdo, 'price_book', 'tax_code_name')) {
    fwrite(STDERR, "ERRORE: impossibile creare tax_code_name\n");
    exit(1);
}

$upd = $pdo->exec(
    'UPDATE price_book pb
     INNER JOIN tax_code tc ON tc.id = pb.tax_code_id AND tc.deleted = 0
     SET pb.tax_code_name = tc.code
     WHERE pb.deleted = 0
       AND pb.tax_code_id IS NOT NULL
       AND (pb.tax_code_name IS NULL OR pb.tax_code_name = \'\')'
);

fwrite(STDOUT, "Backfill tax_code_name: {$upd} listini\n");
fwrite(STDOUT, "OK. Riprova Modifica su riga ProductPrice nel listino.\n");
