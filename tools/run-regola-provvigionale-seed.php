<?php

/**
 * Crea tabella regole (via rebuild) e inserisce seed Ariel/Arquati se mancanti.
 *
 * Uso: php tools/run-regola-provvigionale-seed.php
 */

require dirname(__DIR__) . '/bootstrap.php';

$app = new Espo\Core\Application();
$app->setupSystemUser();

$pdo = $app->getContainer()->get('entityManager')->getPDO();

$schemaPatch = __DIR__ . '/run-regola-provvigionale-schema-patch.php';

if (is_file($schemaPatch)) {
    echo "=== Schema patch regola_provvigionale ===\n";
    passthru('php ' . escapeshellarg($schemaPatch), $patchExit);

    if ($patchExit !== 0) {
        echo "WARN schema patch exit {$patchExit}\n";
    }

    echo "\n";
}

$files = [
    __DIR__ . '/../database/2026-05-26-gdl-ariel-2026-regole-provvigioni-seed.sql',
    __DIR__ . '/../database/2026-05-26-arquati-pnc-regole-provvigioni-seed.sql',
    __DIR__ . '/../database/2026-07-07-ariel-legacy-scalette-minus-seed.sql',
];

foreach ($files as $file) {
    if (!is_file($file)) {
        echo "SKIP file mancante: {$file}\n";
        continue;
    }

    echo "=== {$file} ===\n";
    $sql = file_get_contents($file);

    if ($sql === false) {
        echo "ERRORE lettura\n";
        continue;
    }

    // Rimuovi commenti e statement multipli
    $statements = array_filter(
        array_map('trim', preg_split('/;\s*\n/', $sql)),
        static fn (string $s): bool => $s !== '' && !str_starts_with($s, '--')
    );

    foreach ($statements as $statement) {
        if ($statement === '') {
            continue;
        }

        try {
            $pdo->exec($statement);
        } catch (Throwable $e) {
            echo 'ERR: ' . $e->getMessage() . "\n";
        }
    }
}

$legacyOrmSeed = __DIR__ . '/seed-regole-provvigioni-ariel-legacy.php';

if (is_file($legacyOrmSeed)) {
    echo "\n=== Seed legacy ORM (fallback affidabile) ===\n";
    passthru('php ' . escapeshellarg($legacyOrmSeed), $legacyExit);

    if ($legacyExit !== 0) {
        echo "ERR seed legacy ORM exit {$legacyExit}\n";
        exit(1);
    }
}

$check = $pdo->query("SELECT COUNT(*) FROM regola_provvigionale WHERE deleted = 0");
$count = (int) $check->fetchColumn();

echo "\nRegole attive in DB: {$count}\n";

$required = ['arielPlus35', 'arielBase105', 'arqCpP5', 'arlCliM029', 'arlLegacyPlus50'];
$placeholders = implode(',', array_fill(0, count($required), '?'));
$stmt = $pdo->prepare(
    "SELECT id FROM regola_provvigionale WHERE deleted = 0 AND id IN ({$placeholders})"
);
$stmt->execute($required);
$found = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($required as $id) {
    if (in_array($id, $found, true)) {
        echo "OK   regola {$id}\n";
    } else {
        echo "ERR  regola mancante: {$id}\n";
        exit(1);
    }
}

echo "Seed regole provvigionali completato.\n";
