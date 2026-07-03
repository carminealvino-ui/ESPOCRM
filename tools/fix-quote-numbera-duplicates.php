<?php

/**
 * Risolve duplicati su quote.number_a prima del rebuild (indice UNIQUE).
 * Uso: php tools/fix-quote-numbera-duplicates.php [--dry-run]
 */

$dryRun = in_array('--dry-run', $argv, true);

require dirname(__DIR__) . '/bootstrap.php';

$app = new Espo\Core\Application();
$app->setupSystemUser();

$pdo = $app->getContainer()->get('entityManager')->getPDO();

$dupStmt = $pdo->query(
    "SELECT number_a, COUNT(*) AS cnt
     FROM quote
     WHERE deleted = 0
       AND number_a IS NOT NULL
       AND TRIM(number_a) != ''
     GROUP BY number_a
     HAVING cnt > 1"
);

$duplicates = $dupStmt->fetchAll(PDO::FETCH_ASSOC);

if ($duplicates === []) {
    echo "Nessun duplicato su number_a.\n";
    exit(0);
}

echo ($dryRun ? '[DRY-RUN] ' : '') . 'Duplicati trovati: ' . count($duplicates) . PHP_EOL;

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

$update = $pdo->prepare('UPDATE quote SET number_a = :code WHERE id = :id AND deleted = 0');

foreach ($duplicates as $dup) {
    $code = (string) $dup['number_a'];
    echo "Duplicato: {$code} (x{$dup['cnt']})" . PHP_EOL;

    $rowsStmt = $pdo->prepare(
        "SELECT id, created_at
         FROM quote
         WHERE deleted = 0 AND number_a = :code
         ORDER BY created_at ASC, id ASC"
    );
    $rowsStmt->execute(['code' => $code]);
    $rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Mantieni il primo record, rinumera gli altri.
    array_shift($rows);

    foreach ($rows as $row) {
        $newCode = $prefix . str_pad((string) $next, $padLength, '0', STR_PAD_LEFT);
        echo "  {$row['id']} -> {$newCode}" . PHP_EOL;

        if (!$dryRun) {
            $update->execute(['code' => $newCode, 'id' => $row['id']]);
        }

        $next++;
    }
}

echo ($dryRun ? 'Dry-run completato.' : 'Duplicati number_a risolti.') . PHP_EOL;
