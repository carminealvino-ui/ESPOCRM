#!/usr/bin/env php
<?php
/**
 * Seed solo regole Ariel LEGACY (scalette minus pre-23/01/2026).
 *
 *   php tools/seed-regole-provvigioni-ariel-legacy.php
 */

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$app = new Espo\Core\Application();
$app->setupSystemUser();

$pdo = $app->getContainer()->get('entityManager')->getPDO();
$file = dirname(__DIR__) . '/database/2026-07-07-ariel-legacy-scalette-minus-seed.sql';

echo "=== Seed Ariel LEGACY scalette minus ===\n";

if (!is_file($file)) {
    fwrite(STDERR, "File mancante: {$file}\n");
    exit(1);
}

$sql = file_get_contents($file);

if ($sql === false) {
    fwrite(STDERR, "ERRORE lettura SQL\n");
    exit(1);
}

$statements = array_filter(
    array_map('trim', preg_split('/;\s*\n/', $sql) ?: []),
    static fn (string $s): bool => $s !== '' && !str_starts_with($s, '--')
);

foreach ($statements as $statement) {
    try {
        $pdo->exec($statement);
    } catch (Throwable $e) {
        echo 'WARN: ' . $e->getMessage() . "\n";
    }
}

$required = [
    'arlCliM029', 'arlCliM499', 'arlCliM699', 'arlCliM999', 'arlCliM1299', 'arlCliM1499',
    'arlCalM290', 'arlCalM1000', 'arlCalMDeep',
    'arlStuM290', 'arlStuM1000', 'arlStuMDeep',
    'arlEcoWind5', 'arlLegacyPlus50',
];

$placeholders = implode(',', array_fill(0, count($required), '?'));
$stmt = $pdo->prepare(
    "SELECT id, percentuale, regime_provvigione, gruppo_provvigione
     FROM regola_provvigionale
     WHERE deleted = 0 AND id IN ({$placeholders})"
);
$stmt->execute($required);
$found = [];

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $found[$row['id']] = $row;
}

$ok = true;

foreach ($required as $id) {
    if (!isset($found[$id])) {
        echo "ERR  regola mancante: {$id}\n";
        $ok = false;
        continue;
    }

    $row = $found[$id];
    echo "OK   {$id} | {$row['regime_provvigione']} | {$row['gruppo_provvigione']} | {$row['percentuale']}%\n";
}

if (!$ok) {
    exit(1);
}

echo "Seed Ariel LEGACY completato.\n";
