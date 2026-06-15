<?php
declare(strict_types=1);
$crmRoot = getcwd();
require_once $crmRoot . '/bootstrap.php';
$app = new \Espo\Core\Application();
$app->setupSystemUser();
$pdo = $app->getContainer()->get('entityManager')->getPDO();
$dataManager = $app->getContainer()->get('dataManager');
function col(PDO $p, string $t, string $c): bool {
    $s = $p->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $s->execute([$t, $c]); return (int)$s->fetchColumn() > 0;
}
echo "=== Fix price_book.tax_code_name ===\n";
if (!col($pdo, 'price_book', 'tax_code_id')) { fwrite(STDERR, "Manca tax_code_id\n"); exit(1); }
if (!col($pdo, 'price_book', 'tax_code_name')) { echo "Rebuild...\n"; $dataManager->rebuild(); }
if (!col($pdo, 'price_book', 'tax_code_name')) {
    echo "ALTER TABLE add tax_code_name\n";
    $pdo->exec('ALTER TABLE price_book ADD COLUMN tax_code_name VARCHAR(100) DEFAULT NULL AFTER tax_code_id');
}
$n = $pdo->exec('UPDATE price_book pb INNER JOIN tax_code tc ON tc.id = pb.tax_code_id AND tc.deleted = 0 SET pb.tax_code_name = tc.code WHERE pb.deleted = 0 AND pb.tax_code_id IS NOT NULL AND (pb.tax_code_name IS NULL OR pb.tax_code_name = \'\')');
echo "Backfill: {$n} listini\nOK.\n";
