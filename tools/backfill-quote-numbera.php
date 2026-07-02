<?php

/**
 * Assegna numberA (Codice Contratto) ai contratti già Presentato senza codice.
 * Uso: php tools/backfill-quote-numbera.php [--dry-run]
 */

$dryRun = in_array('--dry-run', $argv, true);

require dirname(__DIR__) . '/bootstrap.php';

$app = new Espo\Core\Application();
$app->setupSystemUser();

$em = $app->getContainer()->get('entityManager');
$pdo = $em->getPDO();

$stmt = $pdo->query(
    "SELECT id, status, number_a, numero_contratto
     FROM quote
     WHERE deleted = 0
       AND (number_a IS NULL OR TRIM(number_a) = '')
       AND status != 'Draft'
     ORDER BY date_quoted ASC, created_at ASC"
);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($rows === []) {
    echo "Nessun contratto da aggiornare.\n";
    exit(0);
}

$prefix = 'Contratto_';
$padLength = 5;

$maxStmt = $pdo->query(
    "SELECT number_a FROM quote
     WHERE deleted = 0 AND number_a IS NOT NULL AND number_a LIKE 'Contratto_%'
     ORDER BY number_a DESC LIMIT 1"
);
$maxRow = $maxStmt->fetch(PDO::FETCH_ASSOC);
$next = 1;

if ($maxRow && !empty($maxRow['number_a'])) {
    $digits = preg_replace('/\D/', '', (string) $maxRow['number_a']);
    $next = $digits !== '' ? ((int) $digits) + 1 : 1;
}

echo ($dryRun ? '[DRY-RUN] ' : '') . 'Contratti da aggiornare: ' . count($rows) . PHP_EOL;

$update = $pdo->prepare('UPDATE quote SET number_a = :code WHERE id = :id AND deleted = 0');

foreach ($rows as $row) {
    $code = $prefix . str_pad((string) $next, $padLength, '0', STR_PAD_LEFT);
    echo $row['id'] . ' -> ' . $code . ' (status=' . $row['status'] . ')' . PHP_EOL;

    if (!$dryRun) {
        $update->execute(['code' => $code, 'id' => $row['id']]);
    }

    $next++;
}

echo ($dryRun ? 'Dry-run completato.' : 'Backfill numberA completato.') . PHP_EOL;
